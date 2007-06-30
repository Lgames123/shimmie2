<?php

class TagList extends Extension {
	var $theme = null;
	
// event handling {{{
	public function receive_event($event) {
		if($this->theme == null) $this->theme = get_theme_object("tag_list", "TagListTheme");
		
		if(is_a($event, 'PageRequestEvent') && ($event->page == "tags")) {
			global $page;

			$this->theme->set_navigation($this->build_navigation());
			switch($event->get_arg(0)) {
				default:
				case 'map':
					$this->theme->set_heading("Tag Map");
					$this->theme->set_tag_list($this->build_tag_map());
					break;
				case 'alphabetic':
					$this->theme->set_heading("Alphabetic Tag List");
					$this->theme->set_tag_list($this->build_tag_alphabetic());
					break;
				case 'popularity':
					$this->theme->set_heading("Tag List by Popularity");
					$this->theme->set_tag_list($this->build_tag_popularity());
					break;
			}
			$this->theme->display_page($page);
		}
		if(is_a($event, 'PageRequestEvent') && ($event->page == "index")) {
			global $config;
			global $page;
			if($config->get_int('tag_list_length') > 0) {
				if(isset($_GET['search'])) {
					$this->add_refine_block($page, tag_explode($_GET['search']));
				}
				else {
					$this->add_popular_block($page);
				}
			}
		}

		if(is_a($event, 'DisplayingImageEvent')) {
			global $page;
			global $config;
			if($config->get_int('tag_list_length') > 0) {
				$this->add_related_block($page, $event->image);
			}
		}

		if(is_a($event, 'SetupBuildingEvent')) {
			$sb = new SetupBlock("Tag Map Options");
			$sb->add_int_option("tags_min", "Ignore tags used fewer than "); $sb->add_label(" times");
			$event->panel->add_block($sb);

			$sb = new SetupBlock("Popular / Related Tag List");
			$sb->add_int_option("tag_list_length", "Show top "); $sb->add_label(" tags");
			$sb->add_text_option("info_link", "<br>Tag info link: ");
			$sb->add_bool_option("tag_list_numbers", "<br>Show tag counts: ");
			$event->panel->add_block($sb);
		}
		if(is_a($event, 'ConfigSaveEvent')) {
			$event->config->set_int_from_post("tags_min");

			$event->config->set_int_from_post("tag_list_length");
			$event->config->set_string_from_post("info_link");
			$event->config->set_bool_from_post("tag_list_numbers");
		}
	}
// }}}
// misc {{{
	private function tag_link($tag) {
		return make_link("index", "search=".url_escape($tag));
	}
// }}}
// maps {{{
	private function build_navigation() {
		$h_index = "<a href='".make_link("index")."'>Index</a>";
		$h_map = "<a href='".make_link("tags/map")."'>Map</a>";
		$h_alphabetic = "<a href='".make_link("tags/alphabetic")."'>Alphabetic</a>";
		$h_popularity = "<a href='".make_link("tags/popularity")."'>Popularity</a>";
		return "$h_index<br>$h_map<br>$h_alphabetic<br>$h_popularity";	
	}

	private function build_tag_map() {
		global $database;
		global $config;

		$tags_min = $config->get_int('tags_min');
		$result = $database->Execute(
				"SELECT tag,COUNT(image_id) AS count FROM tags GROUP BY tag HAVING count > ? ORDER BY tag",
				array($tags_min));

		$html = "";
		while(!$result->EOF) {
			$row = $result->fields;
			$h_tag = html_escape($row['tag']);
			$count = $row['count'];
			if($count > 1) {
				$size = floor(log(log($row['count'] - $tags_min + 1)+1)*1.5*100)/100;
				$link = $this->tag_link($row['tag']);
				$html .= "&nbsp;<a style='font-size: ${size}em' href='$link'>$h_tag</a>&nbsp;\n";
			}
			$result->MoveNext();
		}
		return $html;
	}

	private function build_tag_alphabetic() {
		global $database;
		global $config;

		$tags_min = $config->get_int('tags_min');
		$result = $database->Execute(
				"SELECT tag,COUNT(image_id) AS count FROM tags GROUP BY tag HAVING count > ? ORDER BY tag",
				array($tags_min));

		$html = "";
		$lastLetter = 0;
		while(!$result->EOF) {
			$row = $result->fields;
			$h_tag = html_escape($row['tag']);
			$count = $row['count'];
			if($lastLetter != strtolower(substr($h_tag, 0, 1))) {
				$lastLetter = strtolower(substr($h_tag, 0, 1));
				$html .= "<p>$lastLetter<br>";
			}
			$link = $this->tag_link($row['tag']);
			$html .= "<a href='$link'>$h_tag&nbsp;($count)</a>\n";
			$result->MoveNext();
		}

		return $html;
	}

	private function build_tag_popularity() {
		global $database;
		global $config;

		$tags_min = $config->get_int('tags_min');
		$result = $database->Execute(
				"SELECT tag,COUNT(image_id) AS count FROM tags GROUP BY tag HAVING count > ? ORDER BY count DESC, tag ASC",
				array($tags_min)
				);

		$html = "Results grouped by log<sub>e</sub>(n)";
		$lastLog = 0;
		while(!$result->EOF) {
			$row = $result->fields;
			$h_tag = html_escape($row['tag']);
			$count = $row['count'];
			if($lastLog != floor(log($count))) {
				$lastLog = floor(log($count));
				$html .= "<p>$lastLog<br>";
			}
			$link = $this->tag_link($row['tag']);
			$html .= "<a href='$link'>$h_tag&nbsp;($count)</a>\n";
			$result->MoveNext();
		}

		return $html;
	}
// }}}
// blocks {{{
	private function add_related_block($page, $image) {
		global $database;
		global $config;

		$query = "
			SELECT COUNT(t3.image_id) as count, t3.tag 
			FROM
				tags AS t1,
				tags AS t2,
				tags AS t3 
			WHERE
				t1.image_id=?
				AND t1.tag=t2.tag
				AND t2.image_id=t3.image_id
				AND t1.tag != 'tagme'
				AND t3.tag != 'tagme'
			GROUP by t3.tag
			ORDER by count DESC
			LIMIT ?
		";
		$args = array($image->id, $config->get_int('tag_list_length'));

		$tags = $database->db->GetAll($query, $args);
		if(count($tags) > 0) {
			$this->theme->display_related_block($page, $tags);
		}
	}

	private function add_popular_block($page) {
		global $database;
		global $config;

		$query = "
			SELECT tag, COUNT(image_id) AS count
			FROM tags
			GROUP BY tag
			ORDER BY count DESC
			LIMIT ?
		";
		$args = array($config->get_int('tag_list_length'));

		$tags = $database->db->GetAll($query, $args);
		if(count($tags) > 0) {
			$this->theme->display_popular_block($page, $tags);
		}
	}

	private function add_refine_block($page, $search) {
		global $database;
		global $config;

		$tags = tag_explode($search);
		$s_tags = array_map("sql_escape", $tags);
		$s_tag_list = join(',', $s_tags);

		$query = "
			SELECT t2.tag, COUNT(t2.image_id) AS count
			FROM
				tags AS t1,
				tags AS t2
			WHERE 
				t1.tag IN($s_tag_list)
				AND t1.image_id=t2.image_id
			GROUP BY t2.tag 
			ORDER BY count
			DESC LIMIT ?
		";
		$args = array($config->get_int('tag_list_length'));

		$tags = $database->db->GetAll($query, $args);
		if(count($tags) > 0) {
			$this->theme->display_refine_block($page, $tags, $search);
		}
	}
// }}}
}
add_event_listener(new TagList());
?>
