<?php
// phpcs:disable Squiz.Commenting.FileComment.WrongStyle

/*
Plugin Name: Widget to Display Posts from Current Category
Description: This plugin allows you to display posts from the current category in the sidebar
Version: 0.2
Author: Alexander Kadyrov
Author URI: http://gruz0.ru/
Text Domain: widget-to-show-posts-in-current-category
License: MIT
License URI: https://github.com/gruz0/widget-to-display-posts-from-current-category/blob/master/LICENSE
*/

// phpcs:enable Squiz.Commenting.FileComment.WrongStyle





defined('ABSPATH') or die('No script kiddies please!');

function gruz0_subcategories_load_widget()
{
	register_widget('Display_Posts_From_Current_Category');
}

add_action('widgets_init', 'gruz0_subcategories_load_widget');

class Display_Posts_From_Current_Category extends WP_Widget
{
	const POSTS_PER_PAGE    = 10;

	function __construct()
	{
		parent::__construct(
			'gruz0_posts_in_current_widget',
			__("Current Post' s Categories", 'widget-to-display-categories-from-current-post-category'),
			array('description' => __('Display posts from the current category', 'widget-to-display-categories-from-current-post-category'))
		);
	}

	public function widget($args, $instance)
	{
		if (! is_category() && ! is_single()) {
			return;
		}


		if (!apply_filters('widget_title', $instance['category_title'])) {
			return;
		}

		//////////////////////////////////////////////////
		// post' s current category, categories

		// Determine current category ID depending on page type
		if ( is_category() ) {
			$current_cat_id = get_queried_object_id();
		} else {
			global $post;
			$postcat        = get_the_category( $post->ID );
			$current_cat_id = ! empty( $postcat ) ? $postcat[0]->term_id : 0;
		}

		if ( $current_cat_id ) {

			$categories_title = apply_filters( 'widget_title', $instance['categories_title'] );
			if ( ! empty( $categories_title ) ) {
				echo $args['before_title'] . $categories_title . $args['after_title'];
			}

			$subcats = get_categories( array( 'taxonomy' => 'category' ) );

			// Find root category by traversing up from current category
			$root_id = $current_cat_id;
			$current = get_term( $root_id, 'category' );
			while ( $current && ! is_wp_error( $current ) && $current->parent != 0 ) {
				$root_id = $current->parent;
				$current = get_term( $root_id, 'category' );
			}

			// Determine display mode based on settings
			$parent_display = isset( $instance['parent_display'] ) ? $instance['parent_display'] : 'show_all_parents';
			$current_term   = get_term( $current_cat_id, 'category' );
			$top_term       = null;

			// show_only_parent and do_not_show_parent now use the same behavior.
			if ( in_array( $parent_display, array( 'show_only_parent', 'do_not_show_parent' ), true ) ) {
				if ( $current_term && ! is_wp_error( $current_term ) && $current_term->parent != 0 ) {
					$top_term = get_term( $current_term->parent, 'category' );
				} else {
					$top_term = $current_term;
				}
			}

			echo '<ul class="wooc_sclist">';

			if ( $top_term && ! is_wp_error( $top_term ) ) {
				if ( 'show_only_parent' === $parent_display ) {
					$top_link = get_term_link( $top_term->slug, $top_term->taxonomy );
					echo '<li><a href="' . esc_url( $top_link ) . '">' . esc_html( $top_term->name ) . '</a>';
					echo '<ul>';
					$this->display_category_tree( $top_term->term_id, $subcats, $current_cat_id, $parent_display );
					echo '</ul>';
					echo '</li>';
				} else {
					// do_not_show_parent: same branch as show_only_parent, but hide parent row.
					$this->display_category_tree( $top_term->term_id, $subcats, $current_cat_id, $parent_display );
				}
			} else {
				// show_all_parents: render root parent on top, then full subtree.
				$root_term = get_term( $root_id, 'category' );
				if ( $root_term && ! is_wp_error( $root_term ) ) {
					$root_link = get_term_link( $root_term->slug, $root_term->taxonomy );
					echo '<li><a href="' . esc_url( $root_link ) . '">' . esc_html( $root_term->name ) . '</a>';
					echo '<ul>';
					$this->display_category_tree( $root_term->term_id, $subcats, $current_cat_id, $parent_display );
					echo '</ul>';
					echo '</li>';
				} else {
					$this->display_category_tree( $root_id, $subcats, $current_cat_id, $parent_display );
				}
			}

			echo '</ul>';

		} // end if ($current_cat_id)

		//////////////////////////////////////////////////
		// latest posts of current category
		if ($instance['posts_per_page']>0) {
			$current_post_id = is_single() ? get_queried_object_id() : 0;
			$latest_posts_scope = isset($instance['latest_posts_scope']) ? $instance['latest_posts_scope'] : 'category';
			
			// @todo: Add validations for $instance values to prevent attack
			if (is_category()) {
				$title         = apply_filters('widget_title', $instance['category_title']);
				$subcategories = array();
				$cat           = get_query_var('cat');
				$categories    = get_categories('child_of=' . $cat);

				if ($categories) {
					foreach ($categories as $category) {
						$subcategories[] = $category->term_id;
					}
				}

				$categories = array_unique($subcategories);
				$categories = $categories ? implode(',', $categories) : $cat;
			} else {
				$title = apply_filters('widget_single_title', $instance['single_title']);

				// In a single post, display the defined category.
				global $post;
				$categories = implode(',', wp_get_post_categories($post->ID));
			}

			// Build query args
			$query_args = array(
				'orderby' => 'ID',
				'order' => 'DESC',
				'post_type' => 'post',
				'post_status' => 'publish',
				'posts_per_page' => $instance['posts_per_page'],
			);
			
			// Only add category filter if scope is 'category'
			if ( $latest_posts_scope === 'category' ) {
				$query_args['cat'] = $categories;
			}

			// @todo: Make order of posts variable
			// @todo: Display posts randomly
			$the_query = new WP_Query( $query_args );


			if ($the_query->have_posts()) {
				if (! empty($title)) {
					echo $args['before_title'] . $title . $args['after_title'];
				}

				echo '<ul class="category-posts">';

				// @todo: Add some style capability
				while ($the_query->have_posts()) {
					$the_query->the_post();
					if ( $current_post_id && get_the_ID() === (int) $current_post_id ) {
						echo '<li><strong><a href="' . esc_url( get_permalink() ) . '" title="' . esc_attr( get_the_title() ) . '">' . esc_html( get_the_title() ) . '</a></strong></li>';
					} else {
						echo '<li><a href="' . esc_url( get_permalink() ) . '" title="' . esc_attr( get_the_title() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
					}
				}

				echo '</ul>';
				wp_reset_postdata();
			}
		}

		//////////////////////////////////////////////////
		//////////////////////////////////////////////////


	}

	/**
	 * Display category tree recursively
	 */
	private function display_category_tree($parent_id, $categories, $current_cat_id, $parent_display = 'show_all_parents', $depth = 0)
	{
		foreach ($categories as $cat) {
			if ($cat->parent == $parent_id) {
				if ($current_cat_id == $cat->term_id) {
					echo '<li><b>';
				} else {
					echo '<li>';
				}

				$link = get_term_link($cat->slug, $cat->taxonomy);
				echo '<a href="' . esc_url($link) . '">' . esc_html($cat->name) . '</a>';

				if ($current_cat_id == $cat->term_id) {
					echo '</b>';
				}

				// Check if this category has children
				$has_children = false;
				foreach ($categories as $check_cat) {
					if ($check_cat->parent == $cat->term_id) {
						$has_children = true;
						break;
					}
				}

				// Recursively display children
				if ($has_children) {
					echo '<ul>';
					$this->display_category_tree($cat->term_id, $categories, $current_cat_id, $parent_display, $depth + 1);
					echo '</ul>';
				}

				echo '</li>';
			}
		}
	}
	public function form($instance)
	{

		// Define defaults.
		$category_title = isset($instance['category_title']) ? $instance['category_title'] : __('From the same category', 'widget-to-display-categories-from-current-post-category');
		$categories_title = isset($instance['categories_title']) ? $instance['categories_title'] : __('Categories', 'widget-to-display-categories-from-current-post-category');
		$single_title   = isset($instance['single_title']) ? $instance['single_title'] : __('More posts from this section', 'widget-to-display-categories-from-current-post-category');
		$posts_per_page = isset($instance['posts_per_page']) ? absint($instance['posts_per_page']) : self::POSTS_PER_PAGE;
		$parent_display = isset($instance['parent_display']) ? $instance['parent_display'] : 'show_all_parents';
		$latest_posts_scope = isset($instance['latest_posts_scope']) ? $instance['latest_posts_scope'] : 'category';

?>
		<p>
			<label for="<?php echo $this->get_field_id('category_title'); ?>"><?php _e('Category page widget title', 'widget-to-display-categories-from-current-post-category'); ?>:</label>
			<input
				class="widefat" id="<?php echo $this->get_field_id('category_title'); ?>"
				name="<?php echo $this->get_field_name('category_title'); ?>" type="text"
				value="<?php echo esc_attr($category_title); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('categories_title'); ?>"><?php _e('Categories widget title', 'widget-to-display-categories-from-current-post-category'); ?>:</label>
			<input
				class="widefat" id="<?php echo $this->get_field_id('categories_title'); ?>"
				name="<?php echo $this->get_field_name('categories_title'); ?>" type="text"
				value="<?php echo esc_attr($categories_title); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('single_title'); ?>"><?php _e('Single post widget title', 'widget-to-display-categories-from-current-post-category'); ?>:</label>
			<input
				class="widefat" id="<?php echo $this->get_field_id('single_title'); ?>"
				name="<?php echo $this->get_field_name('single_title'); ?>" type="text"
				value="<?php echo esc_attr($single_title); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('posts_per_page'); ?>"><?php _e('Number of latest posts to display (0 to deactivate)', 'widget-to-display-categories-from-current-post-category'); ?>:</label>
			<input
				class="widefat" id="<?php echo $this->get_field_id('posts_per_page'); ?>"
				name="<?php echo $this->get_field_name('posts_per_page'); ?>" type="text"
				value="<?php echo esc_attr($posts_per_page); ?>" maxlength="2" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('parent_display'); ?>"><?php _e('Parent Categories Display', 'widget-to-display-categories-from-current-post-category'); ?>:</label>
			<select class="widefat" id="<?php echo $this->get_field_id('parent_display'); ?>" name="<?php echo $this->get_field_name('parent_display'); ?>">
				<option value="show_all_parents" <?php selected($parent_display, 'show_all_parents'); ?>><?php _e('Show all parents', 'widget-to-display-categories-from-current-post-category'); ?></option>
				<option value="show_only_parent" <?php selected($parent_display, 'show_only_parent'); ?>><?php _e('Show parent', 'widget-to-display-categories-from-current-post-category'); ?></option>
				<option value="do_not_show_parent" <?php selected($parent_display, 'do_not_show_parent'); ?>><?php _e('Do not show parent', 'widget-to-display-categories-from-current-post-category'); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('latest_posts_scope'); ?>"><?php _e('Latest Articles Scope', 'widget-to-display-categories-from-current-post-category'); ?>:</label>
			<select class="widefat" id="<?php echo $this->get_field_id('latest_posts_scope'); ?>" name="<?php echo $this->get_field_name('latest_posts_scope'); ?>">
				<option value="category" <?php selected($latest_posts_scope, 'category'); ?>><?php _e('Latest articles on selected category', 'widget-to-display-categories-from-current-post-category'); ?></option>
				<option value="general" <?php selected($latest_posts_scope, 'general'); ?>><?php _e('Latest articles general', 'widget-to-display-categories-from-current-post-category'); ?></option>
			</select>
		</p>

<?php
	}

	/**
	 * Save or Update old instances with new one
	 */
	public function update($new_instance, $old_instance)
	{

		$instance                   = array();
		$instance['category_title'] = strip_tags(mb_trim($new_instance['category_title']));
		$instance['single_title']   = strip_tags(mb_trim($new_instance['single_title']));
		$instance['categories_title']   = strip_tags(mb_trim($new_instance['categories_title']));

		if (empty($new_instance['posts_per_page'])) {
			$instance['posts_per_page'] = self::POSTS_PER_PAGE;
		} else {
			$new_posts_per_page         = absint(strip_tags($new_instance['posts_per_page']));
			$instance['posts_per_page'] = 0 === $new_posts_per_page ? self::POSTS_PER_PAGE : $new_posts_per_page;
		}

		$instance['parent_display'] = isset($new_instance['parent_display']) ? sanitize_text_field($new_instance['parent_display']) : 'show_all_parents';
		$instance['latest_posts_scope'] = isset($new_instance['latest_posts_scope']) ? sanitize_text_field($new_instance['latest_posts_scope']) : 'category';

		return $instance;
	}
}

// Function to fix multibyte.
if (! function_exists('mb_trim')) {
	function mb_trim($string)
	{
		return preg_replace('/(^\s+)|(\s+$)/us', '', $string);
	}
}

if (! function_exists('write_log')) {
	function write_log($log)
	{
		if (is_array($log) || is_object($log)) {
			error_log(print_r($log, true));
		} else {
			error_log($log);
		}
	}
}

