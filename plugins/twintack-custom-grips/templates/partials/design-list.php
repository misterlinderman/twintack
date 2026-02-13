<?php
/**
 * Design list partial — table on desktop, cards on mobile.
 *
 * Included from templates/dashboard.php.
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Build query args from URL params (check both $_GET and query_var for pagination)
$paged_from_url = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;
$paged_from_rewrite = absint( get_query_var( 'paged', 0 ) );
$current_paged = max( 1, $paged_from_url, $paged_from_rewrite );

$query_args = array(
    'status'  => $active_status,
    'search'  => $search_term,
    'orderby' => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date',
    'order'   => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC',
    'paged'   => $current_paged,
);

$designs = TTCG_Dashboard::query_designs( $query_args );
?>

<div class="ttcg-list">
    <!-- List Header -->
    <div class="ttcg-list__header">
        <h2 class="ttcg-list__title">
            <?php
            if ( ! empty( $active_status ) ) {
                echo esc_html( TTCG_Dashboard::get_status_label( $active_status ) );
            } elseif ( ! empty( $search_term ) ) {
                /* translators: %s: search term */
                printf( esc_html__( 'Results for "%s"', 'twintack-custom-grips' ), esc_html( $search_term ) );
            } else {
                esc_html_e( 'All Grip Designs', 'twintack-custom-grips' );
            }
            ?>
            <span class="ttcg-list__count">(<?php echo esc_html( $designs->found_posts ); ?>)</span>
        </h2>

        <div class="ttcg-list__sort">
            <label for="ttcg-sort" class="ttcg-sr-only"><?php esc_html_e( 'Sort by', 'twintack-custom-grips' ); ?></label>
            <select id="ttcg-sort" class="ttcg-list__sort-select" data-base-url="<?php echo esc_url( TTCG_Router::get_dashboard_url() ); ?>">
                <option value="date-DESC" <?php selected( $query_args['orderby'] . '-' . $query_args['order'], 'date-DESC' ); ?>><?php esc_html_e( 'Newest First', 'twintack-custom-grips' ); ?></option>
                <option value="date-ASC" <?php selected( $query_args['orderby'] . '-' . $query_args['order'], 'date-ASC' ); ?>><?php esc_html_e( 'Oldest First', 'twintack-custom-grips' ); ?></option>
                <option value="customer_name-ASC" <?php selected( $query_args['orderby'] . '-' . $query_args['order'], 'customer_name-ASC' ); ?>><?php esc_html_e( 'Customer A-Z', 'twintack-custom-grips' ); ?></option>
                <option value="status-ASC" <?php selected( $query_args['orderby'] . '-' . $query_args['order'], 'status-ASC' ); ?>><?php esc_html_e( 'Status', 'twintack-custom-grips' ); ?></option>
            </select>
        </div>
    </div>

    <?php if ( $designs->have_posts() ) : ?>

        <!-- Desktop Table -->
        <div class="ttcg-list__table-wrap">
            <table class="ttcg-list__table">
                <thead>
                    <tr>
                        <th class="ttcg-list__th ttcg-list__th--thumb">&nbsp;</th>
                        <th class="ttcg-list__th"><?php esc_html_e( 'Design', 'twintack-custom-grips' ); ?></th>
                        <th class="ttcg-list__th"><?php esc_html_e( 'Customer', 'twintack-custom-grips' ); ?></th>
                        <th class="ttcg-list__th"><?php esc_html_e( 'Team / School', 'twintack-custom-grips' ); ?></th>
                        <th class="ttcg-list__th"><?php esc_html_e( 'Qty', 'twintack-custom-grips' ); ?></th>
                        <th class="ttcg-list__th"><?php esc_html_e( 'Status', 'twintack-custom-grips' ); ?></th>
                        <th class="ttcg-list__th"><?php esc_html_e( 'Messages', 'twintack-custom-grips' ); ?></th>
                        <th class="ttcg-list__th"><?php esc_html_e( 'Date', 'twintack-custom-grips' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ( $designs->have_posts() ) : $designs->the_post();
                        $data   = TTCG_Dashboard::get_design_data( get_the_ID() );
                        $status = $data['artwork_status'];
                        $unread = TTCG_Messaging::get_unread_count( get_the_ID() );
                    ?>
                        <tr class="ttcg-list__row" data-href="<?php echo esc_url( TTCG_Router::get_design_url( get_the_ID() ) ); ?>">
                            <td class="ttcg-list__td ttcg-list__td--thumb">
                                <?php if ( ! empty( $data['mockup_display_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $data['mockup_display_url'] ); ?>"
                                         alt="<?php echo esc_attr( $data['title'] ); ?>"
                                         class="ttcg-list__thumb-img" loading="lazy">
                                <?php else : ?>
                                    <div class="ttcg-list__thumb-placeholder">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="ttcg-list__td ttcg-list__td--title">
                                <a href="<?php echo esc_url( TTCG_Router::get_design_url( get_the_ID() ) ); ?>" class="ttcg-list__design-link">
                                    <?php echo esc_html( $data['title'] ); ?>
                                </a>
                                <span class="ttcg-list__id">#<?php echo esc_html( get_the_ID() ); ?></span>
                            </td>
                            <td class="ttcg-list__td">
                                <div class="ttcg-list__customer-name"><?php echo esc_html( $data['customer_name'] ); ?></div>
                                <div class="ttcg-list__customer-email"><?php echo esc_html( $data['customer_email'] ); ?></div>
                            </td>
                            <td class="ttcg-list__td"><?php echo esc_html( $data['team_name'] ); ?></td>
                            <td class="ttcg-list__td"><?php echo esc_html( $data['quantity'] ); ?></td>
                            <td class="ttcg-list__td">
                                <span class="ttcg-status-badge" style="background-color: <?php echo esc_attr( TTCG_Dashboard::get_status_color( $status ) ); ?>;">
                                    <?php echo esc_html( TTCG_Dashboard::get_status_label( $status ) ); ?>
                                </span>
                            </td>
                            <td class="ttcg-list__td ttcg-list__td--messages">
                                <?php if ( $unread > 0 ) : ?>
                                    <span class="ttcg-unread-badge" title="<?php echo esc_attr( sprintf( '%d unread', $unread ) ); ?>"><?php echo esc_html( $unread ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="ttcg-list__td ttcg-list__td--date"><?php echo esc_html( $data['date'] ); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards (visible below 768px) -->
        <div class="ttcg-list__cards">
            <?php
            $designs->rewind_posts();
            while ( $designs->have_posts() ) : $designs->the_post();
                $data   = TTCG_Dashboard::get_design_data( get_the_ID() );
                $status = $data['artwork_status'];
                $unread = TTCG_Messaging::get_unread_count( get_the_ID() );
            ?>
                <a href="<?php echo esc_url( TTCG_Router::get_design_url( get_the_ID() ) ); ?>" class="ttcg-card">
                    <div class="ttcg-card__header">
                        <?php if ( ! empty( $data['mockup_display_url'] ) ) : ?>
                            <img src="<?php echo esc_url( $data['mockup_display_url'] ); ?>"
                                 alt="<?php echo esc_attr( $data['title'] ); ?>"
                                 class="ttcg-card__thumb" loading="lazy">
                        <?php else : ?>
                            <div class="ttcg-card__thumb-placeholder">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            </div>
                        <?php endif; ?>
                        <div class="ttcg-card__title-area">
                            <h3 class="ttcg-card__title"><?php echo esc_html( $data['title'] ); ?></h3>
                            <span class="ttcg-card__id">#<?php echo esc_html( get_the_ID() ); ?></span>
                        </div>
                        <?php if ( $unread > 0 ) : ?>
                            <span class="ttcg-unread-badge"><?php echo esc_html( $unread ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ttcg-card__body">
                        <div class="ttcg-card__meta">
                            <span><?php echo esc_html( $data['customer_name'] ); ?></span>
                            <span><?php echo esc_html( $data['team_name'] ); ?></span>
                        </div>
                        <div class="ttcg-card__footer">
                            <span class="ttcg-status-badge" style="background-color: <?php echo esc_attr( TTCG_Dashboard::get_status_color( $status ) ); ?>;">
                                <?php echo esc_html( TTCG_Dashboard::get_status_label( $status ) ); ?>
                            </span>
                            <span class="ttcg-card__date"><?php echo esc_html( $data['date'] ); ?></span>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php if ( $designs->max_num_pages > 1 ) : ?>
            <div class="ttcg-pagination">
                <?php
                $current_page_num = max( 1, $query_args['paged'] );
                $total_pages      = $designs->max_num_pages;

                $base_args = array_filter( array(
                    'status'  => $active_status,
                    'search'  => $search_term,
                    'orderby' => $query_args['orderby'],
                    'order'   => $query_args['order'],
                ) );

                if ( $current_page_num > 1 ) :
                    $prev_args = array_merge( $base_args, array( 'paged' => $current_page_num - 1 ) );
                ?>
                    <a href="<?php echo esc_url( TTCG_Router::get_filtered_url( $prev_args ) ); ?>" class="ttcg-pagination__link">&laquo; <?php esc_html_e( 'Previous', 'twintack-custom-grips' ); ?></a>
                <?php endif; ?>

                <span class="ttcg-pagination__info">
                    <?php
                    /* translators: 1: current page, 2: total pages */
                    printf( esc_html__( 'Page %1$d of %2$d', 'twintack-custom-grips' ), $current_page_num, $total_pages );
                    ?>
                </span>

                <?php if ( $current_page_num < $total_pages ) :
                    $next_args = array_merge( $base_args, array( 'paged' => $current_page_num + 1 ) );
                ?>
                    <a href="<?php echo esc_url( TTCG_Router::get_filtered_url( $next_args ) ); ?>" class="ttcg-pagination__link"><?php esc_html_e( 'Next', 'twintack-custom-grips' ); ?> &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else : ?>

        <div class="ttcg-empty">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
            <h3><?php esc_html_e( 'No grip designs found', 'twintack-custom-grips' ); ?></h3>
            <?php if ( ! empty( $search_term ) || ! empty( $active_status ) ) : ?>
                <p><?php esc_html_e( 'Try adjusting your filters or search term.', 'twintack-custom-grips' ); ?></p>
                <a href="<?php echo esc_url( TTCG_Router::get_dashboard_url() ); ?>" class="ttcg-btn ttcg-btn--secondary"><?php esc_html_e( 'Clear Filters', 'twintack-custom-grips' ); ?></a>
            <?php else : ?>
                <p><?php esc_html_e( 'No custom grip designs have been submitted yet.', 'twintack-custom-grips' ); ?></p>
            <?php endif; ?>
        </div>

    <?php endif;

    wp_reset_postdata();
    ?>
</div>
