<?php
defined('ABSPATH') OR exit;
if(!class_exists('Picturefill_WP_Function_Helpers')){
  class Picturefill_WP_Function_Helpers{

    private $filter = '';
    private $cache_duration = 86400;
    private $image_sizes_to_remove = array();
    private $image_size_to_add = '';
    private $insert_before = '';

    public static function retina_only($default_image_sizes, $image_attributes){
      if('full' === $image_attributes['size'][1]){
        return array($image_attributes['size'][1]);
      }else{
        return array(
          $image_attributes['size'][1],
          $image_attributes['size'][1] . '@2x'
        );
      }
    }

    public static function remove_line_breaks($output){
      return str_replace("\n", '', $output);
    }

    public static function min_template($template_file_path, $template, $template_path){
      return $template_path . 'min/' . $template . '-template.php';
    }

    public function apply_to_filter($filter){
      $this->filter = $filter;
      add_filter($filter, array($this, '_apply_picturefill_wp_to_filter'));
    }

    public function set_cache_duration($cache_duration){
      $this->cache_duration = $cache_duration;
      add_filter('picturefill_wp_cache_duration', array($this, '_set_cache_duration'));

    }

    public function remove_image_from_responsive_list($image_size){
      if('string' === gettype($image_size)){
        $this->image_sizes_to_remove = array($image_size, $image_size . '@2x');
      }elseif('array' === gettype($image_size)){
        $this->image_sizes_to_remove = array();
        foreach($image_size as $size){
          $this->image_sizes_to_remove[] = $size;
          $this->image_sizes_to_remove[] = $size . '@2x';
        }
      }
      add_filter('picturefill_wp_image_sizes', array('Picturefill_WP_Function_Helpers', '_remove_image_from_responsive_list'), 10, 2);
    }

    public function add_image_to_responsive_queue($image_size, $insert_before){
      $this->image_size_to_add = $image_size;
      $this->insert_before = $insert_before;

      add_filter('picturefill_wp_image_attachment_data', array($this, '_add_size_attachment_data'), 10, 2);

      add_filter('picturefill_wp_image_sizes', '_add_size_to_responsive_image_list', 11, 2);
    }

    public function apply_to_post_thumbnail(){
      $this->image_size_to_add = 'post-thumbnail';
      add_action('init', array($this, '_add_retina_post_thumbnail'));
      add_filter('post_thumbnail_html', array($this, '_add_size_to_post_thumbnail_class'), 9, 5);
      add_filter('picturefill_wp_image_attachment_data', array($this, '_add_size_attachment_data'), 10, 2);
      add_filter('picturefill_wp_image_sizes', array($this, '_post_thumbnail_sizes'), 10, 2);
      $this->apply_to_filter('post_thumbnail_html');
    }

    public function _post_thumbnail_sizes($default_image_sizes, $image_attributes){
      return 'post-thumbnail' === $image_attributes['size'][1] ? array(
        'post-thumbnail',
        'post-thumbnail@2x'
      ) : $default_image_sizes;
    }

    public function _add_retina_post_thumbnail(){
      global $_wp_additional_image_sizes;
      add_image_size('post-thumbnail@2x', $_wp_additional_image_sizes['post-thumbnail']['width'] * 2, $_wp_additional_image_sizes['post-thumbnail']['height'] * 2, $_wp_additional_image_sizes['post-thumbnail']['crop']);
    }

    public function _add_size_to_post_thumbnail_class($html, $post_id, $post_thumbnail_id, $size, $attr){
      return preg_replace('/class="([^"]+)"/', 'class="$1 size-' . $size . '"', $html);
    }

    public function _add_size_to_responsive_image_list($image_sizes, $image_attributes){
      if('@2x' === substr($this->insert_before, -3)){
        return $image_sizes;
      }

      $position = array_search($this->insert_before, $image_sizes) - 1;

      if($image_attributes['min_size'] !== $this->insert_before){
        if(1 > $position){
          return array_merge(array($this->image_size_to_add, $this->image_size_to_add . '@2x'), $image_sizes);
        }else{
          return array_splice($image_sizes, $position, 0, array($this->image_size_to_add, $this->image_size_to_add . '@2x'));
        }
      }else{
        return $image_sizes;
      }
    }

    public function _add_size_attachment_data($attachment_data, $attachment_id){
     $new_size_data = array(
       $this->image_size_to_add => wp_get_attachment_image_src($attachment_id, $this->image_size_to_add),
       $this->image_size_to_add . '@2x' => wp_get_attachment_image_src($attachment_id, $this->image_size_to_add . '@2x')
     );
     return array_merge($attachment_data, $new_size_data);
    }

    public function _apply_picturefill_wp_to_filter($content){
      return Picturefill_WP::get_instance()->cache_picturefill_output($content, $this->filter);
    }

    public function _set_cache_duration($old_cache_duration){
      return $this->cache_duration;
    }

    public function _remove_image_from_responsive_list($image_sizes, $image_attributes){
      return array_diff($image_sizes, $this->image_sizes_to_remove);
    }
  }
}