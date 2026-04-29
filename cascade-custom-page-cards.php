<?php
/**
 * Plugin Name: Cascade Custom - Page Cards
 * Description: Custom Gutenberg blocks for cellgroupresources.net.
 * Version:     4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Update checker (GitHub releases) ─────────────────────────────────────────

require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$cascade_puc = PucFactory::buildUpdateChecker(
    'https://github.com/cascadewebworks/page-cards/',
    __FILE__,
    'cascade-custom-page-cards'
);

// ── Default and Saved Settings ────────────────────────────────────────────────

function cascade_default_settings() {
    return array(
        'pageCards' => array(
            'cardStyle'         => 'rounded',
            'columns'           => 2,
            'bgColor'           => '#f0f0f0',
            'textColor'         => '#333333',
            'iconType'          => 'mdi',
            'icon'              => 'chevron-right',
            'subtitleSource'    => 'excerpt',
            'cardCount'         => 2,
            'cptIconField'      => 'cpt_icon',
            'cptIconTypeField'  => 'cpt_icon_type',
            'cptSubtitleSource' => 'excerpt',
        ),
    );
}

function cascade_get_settings() {
    $defaults = cascade_default_settings();
    $saved    = get_option( 'cascade_blocks_defaults', array() );

    // Migrate from pre-4.0 childPages/customCards structure.
    if ( ! isset( $saved['pageCards'] ) && ( isset( $saved['childPages'] ) || isset( $saved['customCards'] ) ) ) {
        $old_cp = isset( $saved['childPages'] )  ? $saved['childPages']  : array();
        $old_cc = isset( $saved['customCards'] ) ? $saved['customCards'] : array();
        $saved  = array( 'pageCards' => wp_parse_args( array_merge( $old_cc, $old_cp ), $defaults['pageCards'] ) );
        update_option( 'cascade_blocks_defaults', $saved );
    }

    return array(
        'pageCards' => wp_parse_args(
            isset( $saved['pageCards'] ) ? $saved['pageCards'] : array(),
            $defaults['pageCards']
        ),
    );
}

// ── Asset & Block Registration ────────────────────────────────────────────────

// Priority 20 ensures all CPTs registered at priority 10 are available for get_post_types().
add_action( 'init', 'cascade_register_blocks', 20 );
function cascade_register_blocks() {
    wp_register_script(
        'cascade-page-cards-editor',
        plugin_dir_url( __FILE__ ) . 'blocks.js',
        array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'blocks.js' )
    );

    $public_cpts = get_post_types( array( 'public' => true, 'show_in_rest' => true, '_builtin' => false ), 'objects' );
    $cpt_options = array_values( array_map( function( $cpt ) {
        return array( 'label' => $cpt->labels->name, 'value' => $cpt->name );
    }, $public_cpts ) );

    wp_add_inline_script(
        'cascade-page-cards-editor',
        'var cascadeBlocksDefaults = ' . wp_json_encode( cascade_get_settings() ) . ';' .
        'var cascadePublicCpts = '     . wp_json_encode( $cpt_options )            . ';',
        'before'
    );

    wp_register_style(
        'cascade-page-cards-style',
        plugin_dir_url( __FILE__ ) . 'style.css',
        array(),
        filemtime( plugin_dir_path( __FILE__ ) . 'style.css' )
    );
    wp_register_style(
        'material-design-icons',
        'https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css',
        array(),
        '7.4.47'
    );
    wp_register_style(
        'font-awesome-6',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        array(),
        '6.4.0'
    );

    $d  = cascade_get_settings();
    $pc = $d['pageCards'];

    register_block_type( 'cascade/page-cards', array(
        'api_version'     => 2,
        'editor_script'   => 'cascade-page-cards-editor',
        'style'           => 'cascade-page-cards-style',
        'render_callback' => 'cascade_render_page_cards_block',
        'attributes'      => array(
            'source'    => array( 'type' => 'string',  'default' => 'child-pages' ),
            'cardStyle' => array( 'type' => 'string',  'default' => $pc['cardStyle'] ),
            'columns'   => array( 'type' => 'integer', 'default' => $pc['columns'] ),
            'bgColor'   => array( 'type' => 'string',  'default' => $pc['bgColor'] ),
            'textColor' => array( 'type' => 'string',  'default' => $pc['textColor'] ),
            // Child Pages
            'iconType'       => array( 'type' => 'string', 'default' => $pc['iconType'] ),
            'icon'           => array( 'type' => 'string', 'default' => $pc['icon'] ),
            'iconSvg'        => array( 'type' => 'string', 'default' => '' ),
            'subtitleSource' => array( 'type' => 'string', 'default' => $pc['subtitleSource'] ),
            // Custom Entries
            'cardCount' => array( 'type' => 'integer', 'default' => $pc['cardCount'] ),
            'cards'     => array(
                'type'    => 'array',
                'default' => array(),
                'items'   => array(
                    'type'       => 'object',
                    'properties' => array(
                        'title'       => array( 'type' => 'string', 'default' => '' ),
                        'description' => array( 'type' => 'string', 'default' => '' ),
                        'link'        => array( 'type' => 'string', 'default' => '' ),
                        'icon'        => array( 'type' => 'string', 'default' => '' ),
                        'iconSvg'     => array( 'type' => 'string', 'default' => '' ),
                        'iconType'    => array( 'type' => 'string', 'default' => 'mdi' ),
                    ),
                ),
            ),
            // Custom Post Type
            'postType'          => array( 'type' => 'string', 'default' => '' ),
            'cptIconField'      => array( 'type' => 'string', 'default' => $pc['cptIconField'] ),
            'cptIconTypeField'  => array( 'type' => 'string', 'default' => $pc['cptIconTypeField'] ),
            'cptSubtitleSource' => array( 'type' => 'string', 'default' => $pc['cptSubtitleSource'] ),
            'cptSubtitleField'  => array( 'type' => 'string', 'default' => '' ),
        ),
    ) );
}

add_action( 'enqueue_block_editor_assets', 'cascade_enqueue_editor_icon_libs' );
function cascade_enqueue_editor_icon_libs() {
    wp_enqueue_style( 'material-design-icons' );
    wp_enqueue_style( 'font-awesome-6' );
    wp_enqueue_style( 'dashicons' );
}

// ── Admin Settings ────────────────────────────────────────────────────────────

add_filter( 'plugin_action_links_cascade-custom-page-cards/cascade-custom-page-cards.php', 'cascade_plugin_action_links' );
function cascade_plugin_action_links( $links ) {
    array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=cascade-page-cards' ) . '">Settings</a>' );
    return $links;
}

add_action( 'admin_init', 'cascade_register_settings' );
function cascade_register_settings() {
    register_setting( 'cascade_blocks_options', 'cascade_blocks_defaults', array(
        'sanitize_callback' => 'cascade_sanitize_settings',
    ) );
}

add_action( 'admin_menu', 'cascade_add_settings_page' );
function cascade_add_settings_page() {
    add_submenu_page( null, 'Page Cards Defaults', 'Page Cards Defaults', 'manage_options', 'cascade-page-cards', 'cascade_render_settings_page' );
}

add_action( 'admin_enqueue_scripts', 'cascade_enqueue_settings_assets' );
function cascade_enqueue_settings_assets( $hook ) {
    if ( $hook !== 'admin_page_cascade-page-cards' ) { return; }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script( 'wp-color-picker',
        'jQuery(function($){ $(".cascade-color-picker").wpColorPicker(); });'
    );
}

function cascade_sanitize_settings( $input ) {
    $settings         = cascade_default_settings();
    $card_styles      = array( 'rounded', 'flat', 'guide' );
    $column_options   = array( 1, 2, 3, 4 );
    $icon_types       = array( 'mdi', 'fa', 'dashicons', 'svg' );
    $subtitle_sources = array( 'excerpt', 'page_description', 'none' );
    $cpt_sub_sources  = array( 'excerpt', 'custom', 'none' );

    if ( isset( $input['pageCards'] ) ) {
        $pc = $input['pageCards'];
        if ( isset( $pc['cardStyle'] ) && in_array( $pc['cardStyle'], $card_styles, true ) )
            $settings['pageCards']['cardStyle'] = $pc['cardStyle'];
        if ( isset( $pc['columns'] ) && in_array( intval( $pc['columns'] ), $column_options, true ) )
            $settings['pageCards']['columns'] = intval( $pc['columns'] );
        if ( isset( $pc['bgColor'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $pc['bgColor'] ) )
            $settings['pageCards']['bgColor'] = $pc['bgColor'];
        if ( isset( $pc['textColor'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $pc['textColor'] ) )
            $settings['pageCards']['textColor'] = $pc['textColor'];
        if ( isset( $pc['iconType'] ) && in_array( $pc['iconType'], $icon_types, true ) )
            $settings['pageCards']['iconType'] = $pc['iconType'];
        if ( isset( $pc['icon'] ) )
            $settings['pageCards']['icon'] = sanitize_text_field( $pc['icon'] );
        if ( isset( $pc['subtitleSource'] ) && in_array( $pc['subtitleSource'], $subtitle_sources, true ) )
            $settings['pageCards']['subtitleSource'] = $pc['subtitleSource'];
        $cnt = isset( $pc['cardCount'] ) ? intval( $pc['cardCount'] ) : 2;
        if ( $cnt >= 1 && $cnt <= 12 )
            $settings['pageCards']['cardCount'] = $cnt;
        if ( isset( $pc['cptIconField'] ) )
            $settings['pageCards']['cptIconField'] = sanitize_key( $pc['cptIconField'] );
        if ( isset( $pc['cptIconTypeField'] ) )
            $settings['pageCards']['cptIconTypeField'] = sanitize_key( $pc['cptIconTypeField'] );
        if ( isset( $pc['cptSubtitleSource'] ) && in_array( $pc['cptSubtitleSource'], $cpt_sub_sources, true ) )
            $settings['pageCards']['cptSubtitleSource'] = $pc['cptSubtitleSource'];
    }

    return $settings;
}

function cascade_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $s  = cascade_get_settings();
    $pc = $s['pageCards'];
    ?>
    <div class="wrap">
        <h1>Page Cards Defaults</h1>
        <p>These values pre-fill new block instances. Use "Reset to default" in a block's settings sidebar to restore any field to these values.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'cascade_blocks_options' ); ?>

            <h2>Shared</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="pc_cardStyle">Card Style</label></th>
                    <td>
                        <select name="cascade_blocks_defaults[pageCards][cardStyle]" id="pc_cardStyle">
                            <option value="rounded" <?php selected( $pc['cardStyle'], 'rounded' ); ?>>Rounded</option>
                            <option value="flat"    <?php selected( $pc['cardStyle'], 'flat' ); ?>>Flat</option>
                            <option value="guide"   <?php selected( $pc['cardStyle'], 'guide' ); ?>>Guide</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pc_columns">Desktop Columns</label></th>
                    <td>
                        <select name="cascade_blocks_defaults[pageCards][columns]" id="pc_columns">
                            <?php foreach ( array(1,2,3,4) as $n ) : ?>
                            <option value="<?php echo $n; ?>" <?php selected( $pc['columns'], $n ); ?>><?php echo $n; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pc_bgColor">Background / Accent Color</label></th>
                    <td><input type="text" name="cascade_blocks_defaults[pageCards][bgColor]" id="pc_bgColor" value="<?php echo esc_attr( $pc['bgColor'] ); ?>" class="cascade-color-picker"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="pc_textColor">Text Color</label></th>
                    <td><input type="text" name="cascade_blocks_defaults[pageCards][textColor]" id="pc_textColor" value="<?php echo esc_attr( $pc['textColor'] ); ?>" class="cascade-color-picker"></td>
                </tr>
            </table>

            <h2>Child Pages Source</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="pc_iconType">Icon Type</label></th>
                    <td>
                        <select name="cascade_blocks_defaults[pageCards][iconType]" id="pc_iconType">
                            <option value="mdi"       <?php selected( $pc['iconType'], 'mdi' ); ?>>Material Design Icons</option>
                            <option value="fa"        <?php selected( $pc['iconType'], 'fa' ); ?>>Font Awesome</option>
                            <option value="dashicons" <?php selected( $pc['iconType'], 'dashicons' ); ?>>Dashicons (WordPress)</option>
                            <option value="svg"       <?php selected( $pc['iconType'], 'svg' ); ?>>Custom Image / SVG</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pc_icon">Default Icon</label></th>
                    <td>
                        <input type="text" name="cascade_blocks_defaults[pageCards][icon]" id="pc_icon" value="<?php echo esc_attr( $pc['icon'] ); ?>" class="regular-text">
                        <p class="description">MDI slug (e.g. <code>file-document-outline</code>), FA class string (e.g. <code>fa-solid fa-file</code>), or Dashicon name (e.g. <code>admin-home</code>).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pc_subtitleSource">Subtitle Source</label></th>
                    <td>
                        <select name="cascade_blocks_defaults[pageCards][subtitleSource]" id="pc_subtitleSource">
                            <option value="excerpt"          <?php selected( $pc['subtitleSource'], 'excerpt' ); ?>>Excerpt</option>
                            <option value="page_description" <?php selected( $pc['subtitleSource'], 'page_description' ); ?>>Custom Field: page_description</option>
                            <option value="none"             <?php selected( $pc['subtitleSource'], 'none' ); ?>>None</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h2>Custom Entries Source</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="pc_cardCount">Number of Cards</label></th>
                    <td>
                        <select name="cascade_blocks_defaults[pageCards][cardCount]" id="pc_cardCount">
                            <?php for ( $n = 1; $n <= 12; $n++ ) : ?>
                            <option value="<?php echo $n; ?>" <?php selected( $pc['cardCount'], $n ); ?>><?php echo $n; ?></option>
                            <?php endfor; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h2>Custom Post Type Source</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="pc_cptIconField">Icon Field</label></th>
                    <td>
                        <input type="text" name="cascade_blocks_defaults[pageCards][cptIconField]" id="pc_cptIconField" value="<?php echo esc_attr( $pc['cptIconField'] ); ?>" class="regular-text">
                        <p class="description">Post meta key that stores the icon name (e.g. <code>holiday_icon</code>).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pc_cptIconTypeField">Icon Type Field</label></th>
                    <td>
                        <input type="text" name="cascade_blocks_defaults[pageCards][cptIconTypeField]" id="pc_cptIconTypeField" value="<?php echo esc_attr( $pc['cptIconTypeField'] ); ?>" class="regular-text">
                        <p class="description">Post meta key that stores the icon type. Leave blank to assume MDI.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pc_cptSubtitleSource">Subtitle Source</label></th>
                    <td>
                        <select name="cascade_blocks_defaults[pageCards][cptSubtitleSource]" id="pc_cptSubtitleSource">
                            <option value="excerpt" <?php selected( $pc['cptSubtitleSource'], 'excerpt' ); ?>>Post Excerpt</option>
                            <option value="custom"  <?php selected( $pc['cptSubtitleSource'], 'custom' ); ?>>Custom Field</option>
                            <option value="none"    <?php selected( $pc['cptSubtitleSource'], 'none' ); ?>>None</option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Save Defaults' ); ?>
        </form>
    </div>
    <?php
}

// ── Shared utilities ──────────────────────────────────────────────────────────

function cascade_enqueue_icon_style( $icon_type ) {
    switch ( $icon_type ) {
        case 'fa':        wp_enqueue_style( 'font-awesome-6' );       break;
        case 'dashicons': wp_enqueue_style( 'dashicons' );             break;
        case 'svg':                                                     break;
        default:          wp_enqueue_style( 'material-design-icons' );
    }
}

function cascade_build_icon_html( $icon_type, $icon, $icon_svg ) {
    if ( $icon_type === 'svg' && ! empty( $icon_svg ) ) {
        return '<img src="' . esc_url( $icon_svg ) . '" class="child-page-icon-img" alt="" aria-hidden="true">';
    } elseif ( $icon_type === 'fa' ) {
        return '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i>';
    } elseif ( $icon_type === 'dashicons' ) {
        return '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
    } else {
        return '<i class="mdi mdi-' . esc_attr( $icon ) . '" aria-hidden="true"></i>';
    }
}

function cascade_render_card( $card_style, $permalink, $title, $subtitle, $icon_html, $bg_color, $text_color ) {
    $style_class = esc_attr( $card_style );
    ob_start();
    if ( $card_style === 'guide' ) : ?>
        <a href="<?php echo esc_url( $permalink ); ?>" class="child-page-card child-page-card--guide" style="border-left-color: <?php echo $bg_color; ?>;">
            <div class="unit-main">
                <?php if ( $icon_html ) : ?><div class="unit-icon" style="color: <?php echo $bg_color; ?>;"><?php echo $icon_html; ?></div><?php endif; ?>
                <div class="unit-info">
                    <h3 style="color: <?php echo $bg_color; ?>;"><?php echo esc_html( $title ); ?></h3>
                    <?php if ( $subtitle ) : ?><p style="color: <?php echo $text_color; ?>;"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
                </div>
            </div>
        </a>
    <?php else : ?>
        <a href="<?php echo esc_url( $permalink ); ?>" class="child-page-card child-page-card--<?php echo $style_class; ?>" style="background-color: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>;">
            <?php if ( $icon_html ) : ?><div class="child-page-icon"><?php echo $icon_html; ?></div><?php endif; ?>
            <div class="child-page-content">
                <h3 style="color: <?php echo $text_color; ?>;"><?php echo esc_html( $title ); ?></h3>
                <?php if ( $subtitle ) : ?><p><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
            </div>
        </a>
    <?php endif;
    return ob_get_clean();
}

// ── Render: Page Cards ────────────────────────────────────────────────────────

function cascade_render_page_cards_block( $attributes ) {
    $source     = isset( $attributes['source'] ) ? $attributes['source'] : 'child-pages';
    $card_style = $attributes['cardStyle'];
    $columns    = intval( $attributes['columns'] );
    $bg_color   = esc_attr( $attributes['bgColor'] );
    $text_color = esc_attr( $attributes['textColor'] );
    $grid_class = 'child-pages-grid child-pages-grid--cols-' . $columns;

    switch ( $source ) {
        case 'custom': return cascade_render_custom_entries( $attributes, $card_style, $grid_class, $bg_color, $text_color );
        case 'cpt':    return cascade_render_cpt_entries( $attributes, $card_style, $grid_class, $bg_color, $text_color );
        default:       return cascade_render_child_pages( $attributes, $card_style, $grid_class, $bg_color, $text_color );
    }
}

function cascade_render_child_pages( $attributes, $card_style, $grid_class, $bg_color, $text_color ) {
    $parent_id = get_the_ID();
    if ( ! $parent_id || ( is_admin() && ! wp_doing_ajax() ) ) {
        return '<div class="child-pages-block-preview">Page Cards (Child Pages) — preview renders on the frontend.</div>';
    }

    cascade_enqueue_icon_style( $attributes['iconType'] );
    $icon_html = cascade_build_icon_html( $attributes['iconType'], $attributes['icon'], $attributes['iconSvg'] );

    $query = new WP_Query( array(
        'post_type'      => 'page',
        'post_parent'    => $parent_id,
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'OR',
            array( 'key' => 'exclude_from_child_pages', 'value' => '1', 'compare' => '!=' ),
            array( 'key' => 'exclude_from_child_pages', 'compare' => 'NOT EXISTS' ),
        ),
    ) );

    if ( ! $query->have_posts() ) { return ''; }

    ob_start();
    echo '<div class="child-pages-grid-wrapper"><div class="' . $grid_class . '">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $subtitle = '';
        if ( $attributes['subtitleSource'] === 'excerpt' ) {
            $subtitle = get_the_excerpt();
        } elseif ( $attributes['subtitleSource'] === 'page_description' ) {
            $subtitle = get_post_meta( get_the_ID(), 'page_description', true );
        }
        echo cascade_render_card( $card_style, get_permalink(), get_the_title(), $subtitle, $icon_html, $bg_color, $text_color );
    }
    wp_reset_postdata();
    echo '</div></div>';
    return ob_get_clean();
}

function cascade_render_custom_entries( $attributes, $card_style, $grid_class, $bg_color, $text_color ) {
    $cards = isset( $attributes['cards'] ) ? array_slice( $attributes['cards'], 0, intval( $attributes['cardCount'] ) ) : array();
    if ( empty( $cards ) ) { return ''; }

    foreach ( $cards as $card ) {
        cascade_enqueue_icon_style( isset( $card['iconType'] ) ? $card['iconType'] : 'mdi' );
    }

    ob_start();
    echo '<div class="child-pages-grid-wrapper"><div class="' . $grid_class . '">';
    foreach ( $cards as $card ) {
        $title   = isset( $card['title'] ) ? $card['title'] : '';
        $desc    = isset( $card['description'] ) ? $card['description'] : '';
        $link    = isset( $card['link'] ) ? $card['link'] : '';
        if ( ! $title && ! $link ) { continue; }
        $icon_html = cascade_build_icon_html(
            isset( $card['iconType'] ) ? $card['iconType'] : 'mdi',
            isset( $card['icon'] )     ? $card['icon']     : '',
            isset( $card['iconSvg'] )  ? $card['iconSvg']  : ''
        );
        echo cascade_render_card( $card_style, $link, $title, $desc, $icon_html, $bg_color, $text_color );
    }
    echo '</div></div>';
    return ob_get_clean();
}

function cascade_render_cpt_entries( $attributes, $card_style, $grid_class, $bg_color, $text_color ) {
    $post_type = sanitize_key( isset( $attributes['postType'] ) ? $attributes['postType'] : '' );
    if ( ! $post_type || ! post_type_exists( $post_type ) ) { return ''; }

    $icon_field      = sanitize_key( $attributes['cptIconField'] );
    $icon_type_field = sanitize_key( $attributes['cptIconTypeField'] );
    $subtitle_source = isset( $attributes['cptSubtitleSource'] ) ? $attributes['cptSubtitleSource'] : 'excerpt';
    $subtitle_field  = sanitize_key( isset( $attributes['cptSubtitleField'] ) ? $attributes['cptSubtitleField'] : '' );

    $query = new WP_Query( array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ) );

    if ( ! $query->have_posts() ) { return ''; }

    ob_start();
    echo '<div class="child-pages-grid-wrapper"><div class="' . $grid_class . '">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $icon_name = $icon_field      ? get_post_meta( get_the_ID(), $icon_field,      true ) : '';
        $icon_type = $icon_type_field ? get_post_meta( get_the_ID(), $icon_type_field, true ) : 'mdi';
        if ( ! $icon_type ) { $icon_type = 'mdi'; }
        cascade_enqueue_icon_style( $icon_type );
        $icon_html = cascade_build_icon_html( $icon_type, $icon_name, '' );

        if ( $subtitle_source === 'excerpt' ) {
            $subtitle = get_the_excerpt();
        } elseif ( $subtitle_source === 'custom' && $subtitle_field ) {
            $subtitle = get_post_meta( get_the_ID(), $subtitle_field, true );
        } else {
            $subtitle = '';
        }
        echo cascade_render_card( $card_style, get_permalink(), get_the_title(), $subtitle, $icon_html, $bg_color, $text_color );
    }
    wp_reset_postdata();
    echo '</div></div>';
    return ob_get_clean();
}
