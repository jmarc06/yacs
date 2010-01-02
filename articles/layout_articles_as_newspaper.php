<?php
/**
 * layout articles as newspaper do
 *
 * The three very first articles are displayed side-by-side, with a thumbnail image if one is present.
 *
 * Subsequent articles are listed below an horizontal line, with text only.
 *
 * @author Bernard Paques
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */
Class Layout_articles_as_newspaper extends Layout_interface {

	/**
	 * the preferred number of items for this layout
	 *
	 * @see skins/layout.php
	 */
	function items_per_page() {
		return 100;
	}

	/**
	 * list articles as newspaper do
	 *
	 * @param resource the SQL result
	 * @return string the rendered text
	 *
	 * @see skins/layout.php
	**/
	function &layout(&$result) {
		global $context;

		$text = '';

		// empty list
		if(!SQL::count($result))
			return $text;

		// build a list of articles
		$others = array();
		$item_count = 0;
		include_once $context['path_to_root'].'comments/comments.php';
		include_once $context['path_to_root'].'links/links.php';
		include_once $context['path_to_root'].'overlays/overlay.php';
		while($item =& SQL::fetch($result)) {

			// permalink
			$url =& Articles::get_permalink($item);

			// next item
			$item_count += 1;

			// section opening
			if($item_count == 1) {
				$text .= '<div class="newest">'."\n";
			} elseif($item_count == 2) {
				$text .= '<div class="recent">'."\n";
			}

			// layout first article
			if($item_count == 1) {
				$text .= $this->layout_first($item);

			// layout newest articles
			} elseif($item_count <= 4) {

				// the style to apply
				switch($item_count) {
				case 2:
					$text .= '<div class="west">';
					break;
				case 3:
					$text .= '<div class="center">';
					break;
				case 4:
					$text .= '<div class="east">';
					break;
				}
				$text .= $icon.$this->layout_newest($item).'</div>';

			// layout recent articles
			} else
				$others[ $url ] = $this->layout_recent($item);

			// close newest
			if($item_count == 1)
				$text .= '<br style="clear: left;" /></div>'."\n";

			// extend the recent in case of floats
			elseif($item_count == 4)
				$text .= '<br style="clear: left;" /></div>'."\n";

		}

		// not enough items in the database to fill the south; close it here
		if(($item_count == 2) || ($item_count == 3))
			$text .= '</div>'."\n";

		// build the list of other articles
		if(count($others)) {

			// make box
			$text .= Skin::build_box(i18n::s('Previous pages'), Skin::build_list($others, 'decorated'));
		}

		// end of processing
		SQL::free($result);
		return $text;
	}


	/**
	 * layout one of the newest articles
	 *
	 * @param array the article
	 * @return string the rendered text
	**/
	function layout_first($item) {
		global $context;

		// permalink
		$url =& Articles::get_permalink($item);

		// get the related overlay, if any
		$overlay = Overlay::load($item);

		// get the anchor
		$anchor =& Anchors::get($item['anchor']);

		// the icon to put aside
		$icon = '';
		if($item['thumbnail_url'])
			$icon = $item['thumbnail_url'];
		if($icon)
			$icon = '<img src="'.$icon.'" class="left_image" alt="" />';

		// use the title to label the link
		if(is_object($overlay) && is_callable(array($overlay, 'get_live_title')))
			$title = $overlay->get_live_title($item);
		else
			$title = Codes::beautify_title($item['title']);

		// rating
		if($item['rating_count'] && !(is_object($anchor) && $anchor->has_option('without_rating')))
			$title .= ' '.Skin::build_link(Articles::get_url($item['id'], 'rate'), Skin::build_rating_img((int)round($item['rating_sum'] / $item['rating_count'])), 'basic');

		// pack in a block
		$text = '<h2>'.Skin::build_link($url, $icon.$title, 'basic').'</h2>';

		// the introduction
		$text .= '<p style="margin-top: 0;">';

		// signal restricted and private articles
		if($item['active'] == 'N')
			$text .= PRIVATE_FLAG.' ';
		elseif($item['active'] == 'R')
			$text .= RESTRICTED_FLAG.' ';

		// the author
		$author = '';
		if(isset($context['with_author_information']) && ($context['with_author_information'] == 'Y'))
			$author = sprintf(i18n::s('by %s'), $item['create_name']).' ';

		// date
		$text .= '<span class="details">'.$author.Skin::build_date($item['publish_date']).' - </span>';

		// the introductory text
		if($item['introduction']) {
			$text .= Codes::beautify_introduction($item['introduction'])
				.' '.Skin::build_link($url, i18n::s('More').MORE_IMG, 'basic');
		} elseif(!is_object($overlay))
			$text .= Skin::cap(Codes::beautify($item['description'], $item['options']), 70, $url);

		// end of the introduction
		$text .= '</p>'."\n";

		// insert overlay data, if any
		if(is_object($overlay))
			$text .= $overlay->get_text('list', $item);

		// read the article
		$menu = array( $url => i18n::s('View the page') );

		// info on related files
		if($count = Files::count_for_anchor('article:'.$item['id']))
			$menu[] = Skin::build_link($url.'#files', sprintf(i18n::ns('%d file', '%d files', $count), $count), 'basic');

		// info on related comments
		$link = Comments::get_url('article:'.$item['id'], 'list');
		if($count = Comments::count_for_anchor('article:'.$item['id']))
			$menu[] = Skin::build_link($link, sprintf(i18n::ns('%d comment', '%d comments', $count), $count), 'basic');

		// discuss
		if(Comments::are_allowed($anchor, $item))
			$menu = array_merge($menu, array( Comments::get_url('article:'.$item['id'], 'comment') => i18n::s('Discuss') ));

		// info on related links
		if($count = Links::count_for_anchor('article:'.$item['id']))
			$menu[] = Skin::build_link($url.'#links', sprintf(i18n::ns('%d link', '%d links', $count), $count), 'basic');

		// append a menu
		$text .= BR.Skin::build_list($menu, 'menu');

		return $text;
	}

	/**
	 * layout one of the newest articles
	 *
	 * @param array the article
	 * @return string the rendered text
	 */
	function layout_newest($item) {
		global $context;

		// permalink
		$url =& Articles::get_permalink($item);

		// get the related overlay, if any
		$overlay = Overlay::load($item);

		// get the anchor
		$anchor =& Anchors::get($item['anchor']);

		// the icon to put aside
		$icon = '';
		if($item['thumbnail_url'])
			$icon = $item['thumbnail_url'];
		if($icon)
			$icon = '<img src="'.$icon.'" class="left_image" alt="" />';

		// use the title to label the link
		if(is_object($overlay) && is_callable(array($overlay, 'get_live_title')))
			$title = $overlay->get_live_title($item);
		else
			$title = Codes::beautify_title($item['title']);

		// rating
		if($item['rating_count'] && !(is_object($anchor) && $anchor->has_option('without_rating')))
			$title .= ' '.Skin::build_link(Articles::get_url($item['id'], 'rate'), Skin::build_rating_img((int)round($item['rating_sum'] / $item['rating_count'])), 'basic');

		// pack in a block
		$text = '<h3>'.Skin::build_link($url, $icon.$title, 'basic').'</h3>';

		// the introduction
		$text .= '<p style="margin-top: 0;">';

		// signal restricted and private articles
		if($item['active'] == 'N')
			$text .= PRIVATE_FLAG.' ';
		elseif($item['active'] == 'R')
			$text .= RESTRICTED_FLAG.' ';

		// the author
		$author = '';
		if(isset($context['with_author_information']) && ($context['with_author_information'] == 'Y'))
			$author = sprintf(i18n::s('by %s'), $item['create_name']).' ';

		// date
		$text .= '<span class="details">'.$author.Skin::build_date($item['publish_date']).'</span>';

		// the introductory text
		if($item['introduction'])
			$text .= ' - '.Codes::beautify_introduction($item['introduction']);
		elseif(!is_object($overlay) && $item['description'])
			$text .= ' - '.Skin::cap(Codes::beautify($item['description'], $item['options']), 25, $url);

		// end of the introduction
		$text .= '</p>'."\n";

		// insert overlay data, if any
		if(is_object($overlay))
			$text .= $overlay->get_text('list', $item);

		// read this article
		$text .= '<p class="details right">'.Skin::build_link($url, i18n::s('View the page'), 'basic');

		// discuss
		if(Comments::are_allowed($anchor, $item))
			$text .= BR.Skin::build_link(Comments::get_url('article:'.$item['id'], 'comment'), i18n::s('Discuss'), 'basic');

		// info on related comments
		if($count = Comments::count_for_anchor('article:'.$item['id'])) {
			$link = Comments::get_url('article:'.$item['id'], 'list');
			$text .= ' - '.Skin::build_link($link, sprintf(i18n::ns('%d comment', '%d comments', $count), $count), 'basic');
		}

		// info on related links
		if($count = Links::count_for_anchor('article:'.$item['id']))
			$text .= ' - '.Skin::build_link($url.'#links', sprintf(i18n::ns('%d link', '%d links', $count), $count), 'basic');

		// end of details
		$text .= '</p>';

		return $text;
	}

	/**
	 * layout one recent article
	 *
	 * @param array the article
	 * @return an array ($prefix, $label, $suffix)
	**/
	function layout_recent($item) {
		global $context;

		// permalink
		$url =& Articles::get_permalink($item);

		// get the related overlay, if any
		$overlay = Overlay::load($item);

		// get the anchor
		$anchor =& Anchors::get($item['anchor']);

		// use the title to label the link
		if(is_object($overlay) && is_callable(array($overlay, 'get_live_title')))
			$title = $overlay->get_live_title($item);
		else
			$title = Codes::beautify_title($item['title']);

		// reset everything
		$prefix = $suffix = '';

		// signal restricted and private articles
		if($item['active'] == 'N')
			$prefix .= PRIVATE_FLAG.' ';
		elseif($item['active'] == 'R')
			$prefix .= RESTRICTED_FLAG.' ';

		// rating
		if($item['rating_count'])
			$suffix .= Skin::build_link(Articles::get_url($item['id'], 'rate'), Skin::build_rating_img((int)round($item['rating_sum'] / $item['rating_count'])), 'basic');

		// the introductory text
		if(isset($item['introduction']) && $item['introduction'])
			$suffix .= ' -&nbsp;'.Codes::beautify_introduction($item['introduction']);
		elseif(isset($item['decription']) && $item['decription'])
			$suffix .= ' -&nbsp;'.Skin::cap(Codes::beautify($item['description'], $item['options']), 25);

		// the author
		$author = '';
		if(isset($context['with_author_information']) && ($context['with_author_information'] == 'Y'))
			$author = sprintf(i18n::s('by %s'), $item['create_name']).' ';

		// date
		$suffix .= '<span class="details"> -&nbsp;'.$author.Skin::build_date($item['publish_date']);

		// count comments
		if($count = Comments::count_for_anchor('article:'.$item['id']))
			$suffix .= ' -&nbsp;'.sprintf(i18n::ns('%d comment', '%d comments', $count), $count);

		// end of details
		$suffix .= '</span>';

		// insert an array of links
		return array($prefix, $title, $suffix);
	}
}

?>