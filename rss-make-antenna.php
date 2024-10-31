<?php
/*
Plugin Name: RSS Make Antenna
Plugin URI: https://raizzenet.com/work/plugin-rss-make-antenna
Description: RSSからサイトの更新情報を取得して投稿するプラグインです。
Version: 1.7.2
Author: Kazunari Matsumoto
Author URI: https://raizzenet.com
*/

class MakeAntenna {
	const OPTION_NAME = 'make_antenna';
	const OPTION_GROUP = 'make_antenna_group';
	const OPTION_SECTION = 'make_antenna_section_id';
	const DEFAULT_TEXT_LENGTH = 30;
	const MAX_TEXT_LENGTH = 200;
	private $options;

	public function __construct() {
		register_activation_hook(__FILE__, array($this, 'activation'));
		register_deactivation_hook(__FILE__, array($this, 'deactivation'));
		register_uninstall_hook(__FILE__, 'MakeAntenna::uninstall');

		add_action('admin_menu', array($this, 'add_rss_setting_page'));
		add_action('admin_init', array($this, 'rss_setting_page_init'));

		add_action('my_rss_patrol', array($this, 'do_my_task'));
		add_action('wp', array($this, 'my_activation'));
		add_filter('cron_schedules', array($this, 'add_cron_30min'));
		add_filter('the_permalink', array($this, 'set_antenna_link'));
		add_filter('post_thumbnail_html', array($this, 'get_antenna_thumbnail'),10,3);
		add_filter('has_post_thumbnail', array($this, 'my_has_post_thumbnail'));
		$this->check_my_options();
	}
	private function check_my_options() {
		$this->options = get_option(self::OPTION_NAME);
		$b_on = false;
		if ($this->options == false) {
			$setting = array(
				'thumbnail_id' => '',
				'show_img' => '1',
				'url' => '',
				'text_length' => self::DEFAULT_TEXT_LENGTH,
				'page_in' => '',
				'limit_date' => '',
				'cat_tag' => '',
				'gosite' => '',
			);
			update_option(self::OPTION_NAME, $setting);
			$this->options = get_option(self::OPTION_NAME);
		}
		if ($this->options['thumbnail_id'] == '') {
			$filename = './wp-content/plugins/rss-make-antenna/no_image.png';
			$attachment = array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'no_image.png',
				'post_content'   => '',
				'post_status'    => 'inherit'
			);
			$attach_id = wp_insert_attachment( $attachment, $filename, 0);
			if($attach_id) {
				require_once(ABSPATH . 'wp-admin/includes/image.php');
				$attach_data = wp_generate_attachment_metadata($attach_id, $filename);
				wp_update_attachment_metadata($attach_id,  $attach_data);
				$this->options['thumbnail_id'] = $attach_id;
				update_option(self::OPTION_NAME, $this->options);
			}
		}
		if ($this->options['text_length'] == '') {
			$this->options['text_length'] = self::DEFAULT_TEXT_LENGTH;
			update_option(self::OPTION_NAME, $this->options);
		}
		if ($this->options['image_path'] == '') {
			$this->options['image_path'] = "./wp-content/plugins/rss-make-antenna/no_image.png";
			update_option(self::OPTION_NAME, $this->options);
		}
		if ($this->options['page_in'] == '') {
			$this->options['page_in'] = '0';
			update_option(self::OPTION_NAME, $this->options);
		}
		if ($this->options['limit_date'] == '') {
			$this->options['limit_date'] = '';
			update_option(self::OPTION_NAME, $this->options);
		}
		if ($this->options['cat_tag'] == '') {
			$this->options['cat_tag'] = '';
			update_option(self::OPTION_NAME, $this->options);
		}
		if ($this->options['gosite'] == '') {
			$this->options['gosite'] = '';
			update_option(self::OPTION_NAME, $this->options);
		}
	}
	public function activation() {
		$this->check_my_options();
	}
	public function deactivation() {
		remove_action('my_rss_patrol', array($this, 'do_my_task'));
		remove_action('wp', array($this, 'my_activation'));
		remove_filter('the_permalink', array($this, 'set_antenna_link'));
		remove_filter('post_thumbnail_html', array($this, 'get_antenna_thumbnail'));
		remove_filter('cron_schedules', array($this, 'add_cron_30min'));
		remove_filter('has_post_thumbnail', array($this, 'my_has_post_thumbnail'));
	}
	public static function uninstall() {
		delete_option(self::OPTION_NAME);
	}

	public function add_cron_30min($schedules) {
		$schedules['30min'] = array(
			'interval' => 1800,
			'display' => '30分に1回'
		);
		return $schedules;
	}

	public function my_activation() {
		if (!wp_next_scheduled('my_rss_patrol')) {
			wp_schedule_event(time(), '30min', 'my_rss_patrol');
		}
	}
	public function do_my_task() {
		$make_antenna = new MakeAntenna();
		if ($make_antenna) {
			$make_antenna->read_feed('');
		}
	}

	public function add_rss_setting_page() {
		$page_title = 'RSS make antenna設定';
		$menu_slug = self::OPTION_NAME;
		$capability = 'manage_options';
		add_options_page($page_title, $page_title, $capability, $menu_slug, array($this, 'show_rss_setting_page'));
	}
	public function rss_setting_page_init() {
		register_setting(self::OPTION_GROUP, self::OPTION_NAME, array($this, 'rss_sanitize'));
		add_settings_section(self::OPTION_SECTION, '', '', self::OPTION_NAME);

		add_settings_field('url', 'RSS取得するサイトのfeed', array($this, 'set_rss_site'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('show_img', 'アイキャッチ画像', array($this, 'set_show_img'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('show_contents', '記事の本文を取得する', array($this, 'set_show_contents'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('text_length', '取得する文字数<br />(最大200文字)', array($this, 'set_text_length'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('image_path', 'アイキャッチ画像のURL', array($this, 'set_image_path'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('thumbnail_id', 'ダミーのアイキャッチ画像ID', array($this, 'set_thumbnail_id'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('page_in', '記事ページの中に入る', array($this, 'set_page_in'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('limit_date', '設定日時以前の記事を作成しない', array($this, 'set_limit_date'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('cat_tag', 'サイト名をタグからカテゴリーに変更する', array($this, 'set_cat_tag'), self::OPTION_NAME, self::OPTION_SECTION);
		add_settings_field('gosite', '記事に「このサイトの記事を見る」を追加しない', array($this, 'set_go_site'), self::OPTION_NAME, self::OPTION_SECTION);
	}

	public function show_rss_setting_page() {
		$this->options = get_option(self::OPTION_NAME);
		if ($_GET['settings-updated'] != false) {
			/* var_dump($this->options); echo '<br/>'; */
			if ($this->options['url'] != '') {
				$this->read_feed($this->options['url']);
			}
		}
		echo '<div class="wrap">';
			echo '<h2>RSS取得設定</h2>';
			echo '<form method="post" action="options.php">';
				settings_fields(self::OPTION_GROUP);
				do_settings_sections(self::OPTION_NAME);
				submit_button();
			echo '</form>';
		echo '</div>';
	}

	public function set_rss_site() {
		echo '１つのURL毎に改行してください。<br/>';
		$text = isset($this->options['url']) ? $this->options['url'] : '';
		echo '<textarea name="make_antenna[url]" rows="10" cols="80">' . $text . '</textarea>';
	}
	public function set_show_img() {
		$selected = isset($this->options['show_img']) ? $this->options['show_img'] : '';
		echo '<input type="checkbox" id="show_img" name="make_antenna[show_img]" value="1" ' . checked(1, $selected, false) . '/>';
		echo '<label for="show_img">表示する</label>';
	}
	public function set_show_contents() {
		$selected = isset($this->options['show_contents']) ? $this->options['show_contents'] : '';
		echo '<input type="checkbox" id="show_contents" name="make_antenna[show_contents]" value="1" ' . checked(1, $selected, false) . '/>';
		echo '<label for="show_contents">取得する</label>';
	}
	public function set_text_length() {
		$text = isset($this->options['text_length']) ? $this->options['text_length'] : '';
		echo '<input type="text" id="text_length" name="make_antenna[text_length]" value="' . $text . '" />';
	}
	public function set_image_path() {
		$text = isset($this->options['image_path']) ? $this->options['image_path'] : '';
		echo '<input type="text" id="image_path" name="make_antenna[image_path]" size="80" value="' . $text . '" />';
	}
	public function set_thumbnail_id() {
		$text = isset($this->options['thumbnail_id']) ? $this->options['thumbnail_id'] : '';
		echo '<input type="text" id="make_antenna" name="make_antenna[thumbnail_id]" value="' . $text . '" />';
	}
	public function set_page_in() {
		$selected = isset($this->options['page_in']) ? $this->options['page_in'] : '';
		echo '<input type="checkbox" id="page_in" name="make_antenna[page_in]" value="1" ' . checked(1, $selected, false) . '/>';
		echo '<label for="show_img">ページの中に入る</label>';
	}
	public function set_limit_date() {
		$text = isset($this->options['limit_date']) ? $this->options['limit_date'] : '';
		echo '<input type="text" id="make_antenna" name="make_antenna[limit_date]" value="' . $text . '" />&nbsp;&nbsp;';
		echo '2020年6月1日の場合は、20200601と入力してください';
	}
	public function set_cat_tag() {
		$selected = isset($this->options['cat_tag']) ? $this->options['cat_tag'] : '';
		echo '<input type="checkbox" id="cat_tag" name="make_antenna[cat_tag]" value="1" ' . checked(1, $selected, false) . '/>';
		echo '<label for="show_img">カテゴリーをサイト名にする</label>';
	}
	public function set_go_site() {
		$selected = isset($this->options['gosite']) ? $this->options['gosite'] : '';
		echo '<input type="checkbox" id="gosite" name="make_antenna[gosite]" value="1" ' . checked(1, $selected, false) . '/>';
		echo '<label for="show_img">記事に「このサイトの記事を見る」を追加しない</label>';
	}

	public function rss_sanitize($input) {

		$new_input = array();
		if ($input['thumbnail_id']) {
			$new_input['thumbnail_id'] = $input['thumbnail_id'];
		} else {
			$new_input['thumbnail_id'] = $this->options['thumbnail_id'];
		}
		if ($input['show_contents']) {
			$new_input['show_contents'] = $input['show_contents'];
		} else {
			$new_input['show_contents'] = '0';
		}
		if ($input['text_length']) {
			$count = $input['text_length'];
			if ($count > self::MAX_TEXT_LENGTH) {
				$count = self::MAX_TEXT_LENGTH;
			}
			$new_input['text_length'] = $count;
		} else {
			$new_input['text_length'] = self::DEFAULT_TEXT_LENGTH;
		}
		if ($input['show_img']) {
			$new_input['show_img'] = $input['show_img'];
		} else {
			$new_input['show_img'] = '0';
		}
		if ($input['url']) {
			$new_input['url'] = $input['url'];
		}
		if ($input['image_path']) {
			$new_input['image_path'] = $input['image_path'];
		} else {
			$new_input['image_path'] = '';
		}
		if ($input['page_in']) {
			$new_input['page_in'] = $input['page_in'];
		} else {
			$new_input['page_in'] = '0';
		}
		if ($input['limit_date']) {
			if (strlen($input['limit_date']) == 8) {
				$new_input['limit_date'] = $input['limit_date'];
			} else {
				$new_input['limit_date'] = '';
			}
		} else {
			$new_input['limit_date'] = '';
		}
		if ($input['cat_tag']) {
			$new_input['cat_tag'] = $input['cat_tag'];
		} else {
			$new_input['cat_tag'] = '0';
		}
		if ($input['gosite']) {
			$new_input['gosite'] = $input['gosite'];
		} else {
			$new_input['gosite'] = '0';
		}
		return $new_input;
	}
	public function set_antenna_link() {

		$link = get_post_meta(get_the_ID(), 'blog-url', true);
		$option = get_option(self::OPTION_NAME);
		if ($link && $option['page_in'] != '1') {
			if (!is_single()) {
				$link .= '" target="_blank';
			}
			echo $link;
		} else {
			echo get_permalink(get_the_ID());
		}
	}
	public function get_antenna_thumbnail($html, $post_id, $post_image_id) {

		$img = '';
		if (!is_admin()) {
			$option = get_option(self::OPTION_NAME);
			if ($option['show_img']) {
				$img = get_post_meta(get_the_ID(), 'img-url', true);
				if ($img == '') {
					$img = '<img src="' . $option['image_path'] . '" alt="no title" />';
				}
			}
		}
		return $img;
	}
	public function my_has_post_thumbnail() {

		$option = get_option(self::OPTION_NAME);
		if ($option['show_img'] == '1') {
			return true;
		} else {
			return false;
		}
	}
	public function read_feed($url_str) {
		echo '<script>console.log("RSS取得");</script>';

		if ($url_str == '') {
			$options = get_option(self::OPTION_NAME);
			$url_list = isset($options['url']) ? $options['url'] : '';
		} else {
			$url_list = $url_str;
		}
		$br = array("\r\n", "\r");
		$ss = array("　\n", " \n", "\n　", "\n ");

		if ($url_list) {
			$str = str_replace($br, "\n", $url_list);
			$arr_url = explode("\n", str_replace($ss, "\n", $url_list));

			foreach ($arr_url as $url) {
				$str = $url;
				$pos = strpos($url, ',');
				if ($pos) {
					$str = substr($url, 0, $pos);
				}
				if ($str) {
					if ($str[0] != '#') {
						$this->feed2post($str);
					}
				}
			}
		}
	}
	public function return_30min($seconds) {
		return 1800;
	}

	private function feed2post($url) {

		include_once(ABSPATH . WPINC . '/feed.php');
		add_filter('wp_feed_cache_transient_lifetime', array($this, 'return_30min'));
		$rss = fetch_feed( $url );
		remove_filter('wp_feed_cache_transient_lifetime', array($this, 'return_30min'));

		$maxitems = 10;
		$b_make_post = false;
		if (!is_wp_error($rss)) {
			$site_name = esc_html($rss->get_title());
			$maxitems = $rss->get_item_quantity(10);
			$rss_items = $rss->get_items(0, $maxitems);
		}
		if ($maxitems != 0 && $rss_items) {
			date_default_timezone_set('Asia/Tokyo');
			foreach ($rss_items as $item) {
				$title = esc_html($item->get_title());
				$description = $item->get_description();
				$link = esc_url($item->get_permalink());
				$date =  $item->get_date('Y-m-d H:i:s');

				if ($this->is_limit_date($date)) {
					continue;
				}
				if ($this->is_duplicate_post($link) == false) {
					$thumbnail = $this->search_thumbnail($item);
					$options = get_option(self::OPTION_NAME);
					if ($options['show_contents'] == '1') {
						if ($options['text_length'] != '') {
							$len = $options['text_length'];
						} else {
							$len = self::DEFAULT_TEXT_LENGTH;
						}
						$contents = mb_substr(str_replace(array("\r\n", "\r", "\n"), "", strip_tags($item->get_content())), 0, $len). "...";
					} else {
						$contents = '';
					}
					$new_category = array();
					$categorys = $item->get_categories();
					if ($categorys) {
						foreach ($categorys as $category) {
							$new_category[] = esc_attr($category->get_label());
						}
					}
					$this->make_post($title, $link, $date, $thumbnail, $new_category, $site_name, $contents);
				}
			}
		}
	}
	private function get_image_id($url) {

		global $wpdb;
		$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url));
		return $attachment[0];
	}
	private function is_limit_date($entry_date) {
		$options = get_option(self::OPTION_NAME);
		if ($options['limit_date'] != '') {
			list($year, $month, $day) = sscanf($options['limit_date'], '%04d%02d%02d');
			$limit_date = date('Y-m-d H:i:s', mktime(0, 0, 0, $month, $day, $year));
			$limit_time = strtotime($limit_date);
			$entry_time = strtotime($entry_date);
			if ($limit_time > $entry_time) {
				return true;
			}
		}
		return false;
	}
	private function is_duplicate_post($link) {

		global $wpdb;
		$result = $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'blog-url' AND meta_value = %s", $link));
		return $result ? true : false;
	}
	private function search_thumbnail($item) {

		$thumbnail = $pict = '';
		if (preg_match('/<img.+?src=[\'"]([^\'"]+?)[\'"].*?>/msi', $item->get_description(), $image)) {
			if (preg_match('/\.gif$|\.png$|\.jpg$|\.jpeg$|\.bmp$/i', $image[1])) {
				$pict = $image[1];
			}
		} else if (preg_match('/<img.+?src=[\'"]([^\'"]+?)[\'"].*?>/msi', $item->get_content(), $image)) {
			if (preg_match('/\.gif$|\.png$|\.jpg$|\.jpg|\.jpeg$|\.bmp$/i', $image[1])) {
				$pict = $image[1];
			}
		}
		if ($pict) {
			$thumbnail =  '<img src="' . esc_url($pict) . '" alt="thumbnail image"/>';
		} else {
			$thumbnail = '';
		}
		return $thumbnail;
	}
	private function make_post($title, $link, $date, $thumbnail, $category, $site_name, $str) {

		$contents = '';
		if ($thumbnail) {
			$contents = $thumbnail;
		}
		if ($str != '') {
			$contents .= $str;
		}
		$contents .= '<br /><!--more-->';
		$options = get_option(self::OPTION_NAME);
		if ($options['gosite'] != '1') {
			$contents .= '<br/><a href="' . $link . '" target="_blank" >このサイトの記事を見る</a>';
		}
		$post = array(
			'post_content'   => $contents,
			'post_title'     => $title,
			'post_status'    => 'publish',
			'post_type'      => 'post',
			'post_author'    => 1,
			'post_date'      => $date,
		);

		if (substr(ltrim($title), 0, 2) != 'PR')  {
			$id = wp_insert_post( $post );
			if ($id) {
				add_post_meta($id, 'blog-url', $link, true);
				if ($thumbnail) {
					add_post_meta($id, 'img-url', $thumbnail, true);
					if ($options['thumbnail_id']) {
						update_post_meta($id, '_thumbnail_id', $options['thumbnail_id']);
					}
				}
				if ($options['cat_tag'] != '1') {
					if ($category) {
						wp_set_object_terms($id, null, 'category');
						wp_add_object_terms($id, $category, 'category');
					}
					wp_set_post_terms($id, array($site_name), 'post_tag', true);	/* タグにサイト名をセット デフォルト */
				} else {
					if ($category) {
						wp_set_object_terms($id, null, 'category');
						wp_add_object_terms($id, $site_name, 'category');
					}
					wp_set_post_terms($id, $category, 'post_tag', true);	/* タグにカテゴリーをセット */
				}
			}
		}
	}
	public function show() {
		$options = get_option(self::OPTION_NAME);
		var_dump($options); echo '<br/>';
	}
}

if (class_exists('MakeAntenna')) {
	$make_antenna = new MakeAntenna();
}

?>
