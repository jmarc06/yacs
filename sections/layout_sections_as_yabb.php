<?php
/**
 * layout sections as boards in a yabb forum
 *
 * This script layouts sections as boards in a discussion forum.
 *
 * The title of each section is also a link to the section itself.
 * A title attribute of the link displays the reference to use to link to the page.
 *
 * Moderators are listed below each board, if any.
 * Moderators of a board are the members who have been explicitly assigned as editors of the related section.
 *
 * The script also lists children boards, if any.
 * This helps to provide a comprehensive view to forum surfers.
 *
 * @see sections/view.php
 *
 * The initial development of YACS forum has been heavily inspired by YABB.
 *
 * @link http://www.yabbforum.com/ Yet Another Bulletin Board
 *
 * @author Bernard Paques [email]bernard.paques@bigfoot.com[/email]
 * @author GnapZ
 * @author Thierry Pinelli [email]contact@vdp-digital.com[/email]
 * @tester Anatoly
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */
Class Layout_sections_as_yabb extends Layout_interface {

	/**
	 * list sections as topics in a forum
	 *
	 * @param resource the SQL result
	 * @return string the rendered text
	**/
	function &layout(&$result) {
		global $context;

		// empty list
		if(!SQL::count($result)) {
			$output = array();
			return $output;
		}

		// load localized strings
		i18n::bind('sections');

		// flag sections updated recently
		if($context['site_revisit_after'] < 1)
			$context['site_revisit_after'] = 2;
		$dead_line = gmstrftime('%Y-%m-%d %H:%M:%S', mktime(0,0,0,date("m"),date("d")-$context['site_revisit_after'],date("Y")));
		$now = gmstrftime('%Y-%m-%d %H:%M:%S');

		// layout in a table
		$text = Skin::table_prefix('yabb');

		// headers
		$text .= Skin::table_row(array(i18n::s('Board'), 'center='.i18n::s('Topics'), i18n::s('Last post')), 'header');

		// build a list of sections
		$family = '';
		while($item =& SQL::fetch($result)) {

			// change the family
			if($item['family'] != $family) {
				$family = $item['family'];

				$text .= '<tr><td class="family" colspan="3">'.$family.'&nbsp;</td></tr>'."\n";
			}

			// reset everything
			$prefix = $label = $suffix = $icon = '';

			// signal restricted and private sections
			if($item['active'] == 'N')
				$prefix .= PRIVATE_FLAG.' ';
			elseif($item['active'] == 'R')
				$prefix .= RESTRICTED_FLAG.' ';

			// indicate the id in the hovering popup
			$hover = i18n::s('Read the section');
			if(Surfer::is_member())
				$hover .= ' [section='.$item['id'].']';

			// the url to view this item
			$url = Sections::get_url($item['id'], 'view', $item['title'], $item['nick_name']);

			// use the title as a link to the page
			$title =& Skin::build_link($url, Codes::beautify_title($item['title']), 'basic', $hover);

			// also use a clickable thumbnail, if any
			if($item['thumbnail_url'])
				$prefix = Skin::build_link($url, '<img src="'.$item['thumbnail_url'].'" alt="" title="'.encode_field($hover).'" class="left_image"'.EOT, 'basic', $hover)
					.$prefix;

			// flag sections updated recently
			if(($item['expiry_date'] > NULL_DATE) && ($item['expiry_date'] <= $now))
				$suffix = EXPIRED_FLAG.' ';
			elseif($item['create_date'] >= $dead_line)
				$suffix = NEW_FLAG.' ';
			elseif($item['edit_date'] >= $dead_line)
				$suffix = UPDATED_FLAG.' ';

			// board introduction
			if($item['introduction'])
				$suffix .= '<br style="clear: none;"/>'.Codes::beautify($item['introduction']);

			// more details
			$details = '';
			$more = array();

			// board moderators
			if($moderators = Members::list_editors_by_name_for_member('section:'.$item['id'], 0, COMPACT_LIST_SIZE, 'compact'))
				$more[] = sprintf(i18n::ns('Moderator: %s', 'Moderators: %s', count($moderators)), Skin::build_list($moderators, 'comma'));

			// children boards
			if($children =& Sections::list_by_title_for_anchor('section:'.$item['id'], 0, COMPACT_LIST_SIZE, 'compact'))
				$more[] = sprintf(i18n::ns('Child board: %s', 'Child boards: %s', count($children)), Skin::build_list($children, 'comma'));

			// as a compact list
			if(count($more)) {
				$details .= '<ul class="compact">';
				foreach($more as $list_item) {
					$details .= '<li>'.$list_item.'</li>'."\n";
				}
				$details .= '</ul>'."\n";
			}

			// all details
			if($details)
				$details = BR.'<span class="details">'.$details."</span>\n";

			// get last post
			$last_post = '--';
			$article =& Articles::get_newest_for_anchor('section:'.$item['id'], TRUE);
			if($article['id']) {

				// flag articles updated recently
				if(($article['expiry_date'] > NULL_DATE) && ($article['expiry_date'] <= $now))
					$flag = EXPIRED_FLAG.' ';
				elseif($article['create_date'] >= $dead_line)
					$flag = NEW_FLAG.' ';
				elseif($article['edit_date'] >= $dead_line)
					$flag = UPDATED_FLAG.' ';
				else
					$flag = '';

				// title
				$last_post = Skin::build_link(Articles::get_url($article['id'], 'view', $article['title'], $article['nick_name']), Codes::beautify_title($article['title']), 'article');

				// last editor
				if($article['edit_date']) {

					// find a name, if any
					if($article['edit_name']) {

						// label the action
						if(isset($article['edit_action']))
							$action = get_action_label($article['edit_action']);
						else
							$action = i18n::s('edited');

						// name of last editor
						$user = sprintf(i18n::s('%s by %s'), $action, Users::get_link($article['edit_name'], $article['edit_address'], $article['edit_id']));
					}

					$last_post .= $flag.BR.'<span class="tiny">'.$user.' '.Skin::build_date($article['edit_date']).'</span>';
				}

			}

			if(!$count = Articles::count_for_anchor('section:'.$item['id']))
				$count = 0;

			// this is another row of the output
			$text .= Skin::table_row(array($prefix.$title.$suffix.$details, 'center='.$count, $last_post));

		}

		// end of processing
		SQL::free($result);

		$text .= Skin::table_suffix();
		return $text;
	}
}

?>