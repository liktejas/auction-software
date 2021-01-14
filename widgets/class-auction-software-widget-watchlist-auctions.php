<?php
/**
 * The widget-specific functionality for watchlist auctions.
 *
 * @link       https://club.wpeka.com/
 * @since      1.0.0
 *
 * @package    Auction_Software
 * @subpackage Auction_Software/widgets
 */

/**
 * The widget-specific functionality for watchlist auctions.
 *
 * @package    Auction_Software
 * @subpackage Auction_Software/widgets
 * @author     WPeka Club <support@wpeka.com>
 */
class Auction_Software_Widget_Watchlist_Auctions extends WP_Widget {

	/**
	 * Widget css classes.
	 *
	 * @access private
	 * @var string $auctionwidget_cssclass Widget css classes.
	 */
	private $auctionwidget_cssclass;

	/**
	 * Widget description.
	 *
	 * @access private
	 * @var string|void $auctionwidget_description Widget description.
	 */
	private $auctionwidget_description;

	/**
	 * Widget id base.
	 *
	 * @access private
	 * @var string $auctionwidget_idbase Widget id base.
	 */
	private $auctionwidget_idbase;

	/**
	 * Widget name.
	 *
	 * @access private
	 * @var string|void $auctionwidget_name Widget name.
	 */
	private $auctionwidget_name;

	/**
	 * Auction_Software_Widget_Watchlist_Auctions constructor.
	 */
	public function __construct() {

		// Widget variable settings.
		$this->auctionwidget_cssclass    = 'woocommerce widget_watchlist_auctions';
		$this->auctionwidget_description = __( 'Display a list of auctions on users watchlist.', 'auction-software' );
		$this->auctionwidget_idbase      = 'watchlist_auctions';
		$this->auctionwidget_name        = __( 'Auction Software Watchlist Auction', 'auction-software' );

		// Widget settings.
		$widget_ops = array(
			'classname'   => $this->auctionwidget_cssclass,
			'description' => $this->auctionwidget_description,
		);

		// Create the widget.
		parent::__construct( $this->auctionwidget_idbase, $this->auctionwidget_name, $widget_ops );

		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );
	}

	/**
	 * Display functionality for widget.
	 *
	 * @since 1.0.0
	 * @param array $args Arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		global $woocommerce;

		$cache = wp_cache_get( 'widget_watchlist_auctions', 'widget' );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? $args['after_title'] : '';

		$title  = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Watchlist Auctions', 'auction-software' ) : $instance['title'], $instance, $this->id_base );
		$number = 5;
		if ( isset( $instance['number'] ) ) {
			if ( ! is_numeric( $instance['number'] ) ) {
				$number = 10;
			} elseif ( $number < 1 ) {
				$number = 1;
			} elseif ( $number > 15 ) {
				$number = 15;
			}
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id   = get_current_user_id();
		$watchlist = get_user_meta( $user_id, 'auction_watchlist' );
		if ( isset( $watchlist[0] ) && ! empty( $watchlist[0] ) ) {
			$watchlist = explode( ',', $watchlist[0] );
		}

		$auction_types = apply_filters(
			'auction_software_auction_types',
			array(
				'auction_simple',
				'auction_reverse',
			)
		);

		$query_args               = array(
			'posts_per_page' => $number,
			'no_found_rows'  => 1,
			'post_status'    => 'publish',
			'post_type'      => 'product',
		);
		$query_args['post__in']   = $watchlist;
		$query_args['meta_query'] = $woocommerce->query->get_meta_query(); // phpcs:ignore slow query
		$query_args['tax_query']  = array( // phpcs:ignore slow query
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $auction_types,
			),
		);

		$r = new WP_Query( $query_args );

		$content = '';

		if ( $r->have_posts() ) {
			$hide_time = empty( $instance['hide_time'] ) ? 0 : 1;

			$content .= $before_widget;

			if ( $title ) {
				$content .= $before_title . $title . $after_title;
			}

			$content .= '<ul class="product_list_widget">';

			while ( $r->have_posts() ) {
				$r->the_post();

				global $product;

				if ( false === $product->is_ended() ) {
					$content .= '<li>
					<a href="' . get_permalink() . '">
						' . ( has_post_thumbnail() ? get_the_post_thumbnail( $r->post->ID, 'shop_thumbnail' ) : wc_placeholder_img( 'shop_thumbnail' ) ) . ' ' . get_the_title() . '
					</a> ';
					if ( ! empty( $product->get_auction_errors() ) ) {
						$content .= '<span class="auction_error">' . __( 'Please resolve the errors from Product admin.', 'auction-software' ) . '</span>';
					} else {
						if ( true === $product->is_started() ) {
							if ( $product->is_ended() ) {
								$content .= '<span class="auction-current-bid">' . __( 'Winning Bid: ', 'auction-software' ) . wc_price( $product->get_auction_winning_bid() ) . '</span>';
							} else {
								$current_bid_value = $product->get_auction_current_bid();
								if ( 0 === (int) $current_bid_value ) {
									$content .= '<span class="auction-current-bid">' . __( 'No bids yet', 'auction-software' ) . '</span>';
								} else {
									$content .= '<span class="auction-current-bid">' . __( 'Current Bid: ', 'auction-software' ) . wc_price( $current_bid_value ) . '</span>';
								}
							}
						} else {
							$content .= '<span class="auction-no-bid">' . __( 'No bids yet', 'auction-software' ) . '</span>';
						}

						$date_to_or_from = '';
						if ( false === $product->is_started() ) {
							$content        .= '<span class="startEndText' . $product->get_id() . '">' . __( 'Auction Starts in ', 'auction-software' ) . '</span><span class="auctiontime-left timeLeft' . $product->get_id() . '"></span>';
							$date_to_or_from = $product->get_auction_date_from();
						} elseif ( 1 !== (int) $hide_time && ! $product->is_ended() ) {
							$content        .= '<span class="startEndText' . $product->get_id() . '">' . __( 'Auction Ends in ', 'auction-software' ) . '</span><span class="auctiontime-left timeLeft' . $product->get_id() . '"></span>';
							$date_to_or_from = $product->get_auction_date_to();
						}
						if ( $product->is_ended() ) {
							$content .= '<span class="has-finished">' . __( 'Auction finished', 'auction-software' ) . '</span>';
						}

						$content .= "<input type='hidden' class='timeLeftId' name='timeLeftId' value='" . $product->get_id() . "' />";

						$content .= "<input type='hidden' class='timeLeftValue" . $product->get_id() . "' value='" . $date_to_or_from . "' />";

					}
					$content .= '</li>';
				}
			}

			$content .= '</ul>';

			$content .= $after_widget;
		}

		wp_reset_postdata();

		if ( isset( $args['widget_id'] ) ) {
			$cache[ $args['widget_id'] ] = $content;
		}

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		wp_cache_set( 'widget_watchlist_auctions', $cache, 'widget' );

	}

	/**
	 * Widget update functionality.
	 *
	 * @since 1.0.0
	 * @param array $new_instance Widget new instance.
	 * @param array $old_instance Widget old instance.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance              = $old_instance;
		$instance['title']     = wp_strip_all_tags( $new_instance['title'] );
		$instance['number']    = (int) $new_instance['number'];
		$instance['hide_time'] = empty( $new_instance['hide_time'] ) ? 0 : 1;

		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset( $alloptions['widget_watchlist_auctions'] ) ) {
			delete_option( 'widget_watchlist_auctions' );
		}

		return $instance;
	}

	/**
	 * Flush widget cache.
	 *
	 * @since 1.0.0
	 */
	public function flush_widget_cache() {
		wp_cache_delete( 'widget_watchlist_auctions', 'widget' );
	}

	/**
	 * Form function.
	 *
	 * @since 1.0.0
	 * @param array $instance Widget instance.
	 * @return string|void
	 */
	public function form( $instance ) {
		$title     = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : __( 'Watchlist Auctions', 'auction-software' );
		$hide_time = empty( $instance['hide_time'] ) ? 0 : 1;
		if ( ! isset( $instance['number'] ) || ! is_numeric( $instance['number'] ) ) {
			$number = 2;
		} else {
			$number = $instance['number'];
		}
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'auction-software' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
				value="<?php echo esc_attr( $title ); ?>"/></p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_attr_e( 'Number of auctions to show:', 'auction-software' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="text"
				value="<?php echo esc_attr( $number ); ?>" size="3"/></p>
		<p><input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'hide_time' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'hide_time' ) ); ?>"<?php checked( $hide_time ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'hide_time' ) ); ?>"><?php esc_attr_e( 'Hide time left', 'auction-software' ); ?></label>
		</p>
		<?php
	}
}
