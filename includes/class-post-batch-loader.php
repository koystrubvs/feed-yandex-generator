<?php
/**
 * Post Batch Loader
 * 
 * Loads posts in batches to avoid OOM errors.
 * Prevents Out of Memory errors on large catalogs (10k+ records).
 * 
 * @package Yandex_Feed_Generator_Pro
 * @since 4.18.22
 */

if (!defined('ABSPATH')) {
    exit;
}

class YFGP_Post_Batch_Loader {
    
    private $post_type;
    private $batch_size;
    
    /**
     * Constructor
     * 
     * @param string|null $post_type Post type to load (default: from settings)
     * @param int $batch_size Batch size (default 1000)
     */
    public function __construct($post_type = null, $batch_size = 1000) {
        // v4.18.39: Get default post type from settings if not provided
        if (empty($post_type)) {
            $settings = get_option('yfgp_settings', array());
            $post_type = $settings['cpt_doctors'] ?? 'post';
        }
        $this->post_type = $post_type;
        
        // Use Constants if available
        if (class_exists('YFGP_Constants')) {
            $this->batch_size = $batch_size > 0 ? $batch_size : YFGP_Constants::DEFAULT_BATCH_SIZE;
        } else {
            $this->batch_size = $batch_size > 0 ? $batch_size : 1000;
        }
    }
    
    /**
     * Loads posts in batches and processes them via callback
     * 
     * @param callable $callback Function to process each batch of posts
     * @return int Total posts processed
     */
    public function load_posts_in_batches(callable $callback): int {
        $offset = 0;
        $total_processed = 0;
        
        // Get total count for this post type
        $counts = wp_count_posts($this->post_type);
        $total_posts = isset($counts->publish) ? (int) $counts->publish : 0;
        
        if ($total_posts === 0) {
            return 0;
        }
        
        while ($total_processed < $total_posts) {
            $args = array(
                'post_type' => $this->post_type,
                'posts_per_page' => $this->batch_size,
                'offset' => $offset,
                'post_status' => 'publish',
                'orderby' => 'ID',
                'order' => 'ASC',
            );
            
            $posts = get_posts($args);
            
            if (empty($posts)) {
                break; // No more posts
            }
            
            // Process the batch
            $callback($posts);
            
            $offset += $this->batch_size;
            $total_processed += count($posts);
            
            // Prevent memory buildup
            wp_reset_postdata();
            
            // Optional: Clear object cache to free memory
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('posts');
            }
        }
        
        return $total_processed;
    }
    
    /**
     * Get total count of posts for this post type
     * 
     * @return int Total published posts
     */
    public function get_total_count(): int {
        $counts = wp_count_posts($this->post_type);
        return isset($counts->publish) ? (int) $counts->publish : 0;
    }
    
    /**
     * Get posts by IDs in batches (for post__in queries)
     *
     * @since 4.18.22
     * @param array<int> $post_ids Array of post IDs
     * @param string $post_type Post type (default: 'any')
     * @param string $post_status Post status (default: 'any')
     * @return array<\WP_Post> Array of WP_Post objects
     */
    public function get_posts_by_ids(array $post_ids, string $post_type = 'any', string $post_status = 'any'): array {
        if (empty($post_ids)) {
            return array();
        }

        // Chunk IDs to avoid memory issues
        $chunks = array_chunk($post_ids, $this->batch_size);
        $all_posts = array();

        foreach ($chunks as $chunk) {
            $args = array(
                'post__in' => array_map('intval', $chunk),
                'post_type' => $post_type,
                'post_status' => $post_status,
                'posts_per_page' => count($chunk), // Exact count for this chunk
                'orderby' => 'post__in', // Preserve order from post__in
                'ignore_sticky_posts' => true,
                'no_found_rows' => true, // Performance optimization
            );

            $posts = get_posts($args);

            if (!empty($posts)) {
                $all_posts = array_merge($all_posts, $posts);
            }

            // Prevent memory buildup
            wp_reset_postdata();
        }

        return $all_posts;
    }
}

