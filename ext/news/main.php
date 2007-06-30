<?php

class News extends Extension {
	var $theme;

	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("news", "NewsTheme");
		
		if(is_a($event, 'PageRequestEvent') && ($event->page == "index")) {
			global $config;
			if(strlen($config->get_string("news_text")) > 0) {
				$this->theme->display_news($event->page_object, $config->get_string("news_text"));
			}
		}
		if(is_a($event, 'SetupBuildingEvent')) {
			$sb = new SetupBlock("News");
			$sb->add_longtext_option("news_text");
			$event->panel->add_block($sb);
		}
		if(is_a($event, 'ConfigSaveEvent')) {
			$event->config->set_string_from_post("news_text");
		}
	}
}
add_event_listener(new News());
?>
