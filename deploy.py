#!/usr/bin/env python3
"""Deploy the Cascade Custom - Page Cards plugin to cellgroupresources.net."""

import os
import subprocess
import sys
import tempfile
from pathlib import Path

HOST       = "craneandturtle@129.80.57.27"
PORT       = "55520"
WP_ROOT    = "/www/craneandturtle_370/public"
PLUGIN_DIR = f"{WP_ROOT}/wp-content/plugins/cascade-custom-page-cards"
OLD_PLUGIN = "cascade-custom-blocks"
LOCAL_DIR  = Path(__file__).parent

FILES = ["cascade-custom-page-cards.php", "blocks.js", "style.css"]
DIRS  = ["vendor"]

SSH_OPTS = ["-p", PORT, "-o", "StrictHostKeyChecking=accept-new"]
SCP_OPTS = ["-P", PORT, "-o", "StrictHostKeyChecking=accept-new"]

# Migrates cascade/child-pages and cascade/custom-cards to cascade/page-cards.
# Idempotent — skips posts that have already been migrated.
MIGRATION_PHP = r"""<?php
global $wpdb;
$migrations = [
    ['old' => 'cascade/child-pages',  'source' => 'child-pages'],
    ['old' => 'cascade/custom-cards', 'source' => 'custom'],
];
$total = 0;
foreach ($migrations as $m) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s",
        '%wp:' . $m['old'] . ' %'
    ));
    foreach ($rows as $row) {
        $pattern = '/<!-- wp:' . str_replace('/', '\/', $m['old']) . ' (\{.*?\}) \/-->/s';
        $new_content = preg_replace_callback($pattern, function($match) use ($m) {
            $attrs = json_decode($match[1], true) ?: [];
            $attrs = array_merge(['source' => $m['source']], $attrs);
            return '<!-- wp:cascade/page-cards ' . wp_json_encode($attrs) . ' /-->';
        }, $row->post_content);
        if ($new_content !== $row->post_content) {
            $wpdb->update($wpdb->posts, ['post_content' => $new_content], ['ID' => $row->ID]);
            echo "  Migrated post {$row->ID}\n";
            $total++;
        }
    }
}
echo "Block migration complete. Posts updated: $total\n";
"""


def run(cmd, **kwargs):
    subprocess.run(cmd, check=True, **kwargs)

def ssh(remote_cmd):
    run(["ssh", *SSH_OPTS, HOST, remote_cmd])

def scp(local_path, remote_path):
    run(["scp", *SCP_OPTS, str(local_path), f"{HOST}:{remote_path}"])

def scp_dir(local_path, remote_path):
    run(["scp", "-r", *SCP_OPTS, str(local_path), f"{HOST}:{remote_path}"])

def old_plugin_exists():
    result = subprocess.run(
        ["ssh", *SSH_OPTS, HOST,
         f"test -d {WP_ROOT}/wp-content/plugins/{OLD_PLUGIN} && echo yes || echo no"],
        capture_output=True, text=True
    )
    return result.stdout.strip() == "yes"


def main():
    print("Creating remote plugin directory...")
    ssh(f"mkdir -p {PLUGIN_DIR}")

    for filename in FILES:
        print(f"  Uploading {filename}...")
        scp(LOCAL_DIR / filename, f"{PLUGIN_DIR}/{filename}")

    for dirname in DIRS:
        print(f"  Uploading {dirname}/...")
        scp_dir(LOCAL_DIR / dirname, f"{PLUGIN_DIR}/{dirname}")

    # One-time migration: runs only while the old plugin folder still exists on the server.
    if old_plugin_exists():
        print("Migrating block types in post content...")
        remote_script = f"{WP_ROOT}/cascade-migrate-tmp.php"
        with tempfile.NamedTemporaryFile(mode='w', suffix='.php', delete=False) as f:
            f.write(MIGRATION_PHP)
            tmp_path = f.name
        try:
            scp(tmp_path, remote_script)
            ssh(f"cd {WP_ROOT} && wp eval-file {remote_script}")
            ssh(f"rm {remote_script}")
        finally:
            os.unlink(tmp_path)

        print("Removing old plugin...")
        ssh(
            f"cd {WP_ROOT} && "
            f"wp plugin deactivate {OLD_PLUGIN} --quiet 2>/dev/null; "
            f"wp plugin delete {OLD_PLUGIN} --quiet 2>/dev/null; "
            f"true"
        )

    print("Activating plugin...")
    ssh(f"cd {WP_ROOT} && wp plugin activate cascade-custom-page-cards")

    print("\nDone. Plugin is live at cellgroupresources.net.")
    print("To deploy future changes, just run this script again.")


if __name__ == "__main__":
    try:
        main()
    except subprocess.CalledProcessError as e:
        sys.exit(f"Command failed: {e}")
