<?php
/**
 * list new articles in the RSS 2.0 format
 *
 * List up to ten fresh pages for one section only.
 * At the moment, this script gives the list of the ten newest published articles,
 * with following information:
 * - title - the title of the article
 * - url - the absolute url to fetch the article
 * - time - the date and time of article last modification
 * - author - the last contributor to the article
 * - section - the label of the section from where the article is originated
 * - image - the absolute url to fetch a related image, if any
 *
 * Per-request authentication is based on HTTP basic authentication mechanism, as explained in
 * [link=RFC2617]http://www.faqs.org/rfcs/rfc2617.html[/link].
 *
 * @link http://www.faqs.org/rfcs/rfc2617.html HTTP Authentication: Basic and Digest Access Authentication
 *
 * If an unknown user asks for the RSS feed, he will be prompted by his user agent to enter his name and password.
 * This mechanism has been checked with [link=FeedReader 2.7]http://www.feedreader.com/[/link],
 * [link=Internet Explorer 6.0]http://www.microsoft.com/windows/ie/default.mspx[/link],
 * and [link=Mozilla 1.7.3]http://www.mozilla.org/releases/mozilla1.7.3[/link].
 *
 * The RFC explicitly allows for building URLs including the name and the password, as in the following example:
 * [snippet]
 * http://my_name:my_password@my_site/yacs/agents/feed.php
 * [/snippet]
 *
 * Such links can be used in user agents (i.e., a lot of poorly written news readers) that do not handle HTTP authentication properly.
 * However, please note that Microsoft has recently [link=removed support for such URLs]http://internetnews.com/dev-news/article.php/3305741[/link].
 * This may means that some news readers based on Internet Explorer won't be able to get YACS events log.
 * If this occurs, please consider to switch to standard-conformant software such as [link=Feedreader]http://www.feedreader.com/[/link].
 *
 * @link http://support.microsoft.com/default.aspx?scid=kb;[LN];834489	Microsoft Knowledge Base Article - 834489
 *
 * If following features are enabled, this script will use them:
 * - compression - Through gzip, we have observed a shift from 3566 bytes to 881 bytes, meaning one Ethernet frame rather than three
 * - cache - Cache is supported through ETag and by setting Content-Length; Also, Cache-Control enables caching for some time, even through HTTPS
 *
 * Restrictions apply on this page:
 * - associates and editors are allowed to move forward
 * - permission is denied if the anchor is not viewable
 * - access is restricted ('active' field == 'R'), but the surfer is an authenticated member
 * - public access is allowed ('active' field == 'Y')
 * - permission denied is the default
 *
 * Accept following invocations:
 * - feed.php/12
 * - feed.php?id=12
 *
 * @link http://blogs.law.harvard.edu/tech/rss RSS 2.0 Specification
 *
 * If this section, or one of its anchor, specifies a specific skin (option keyword '[code]skin_xyz[/code]'),
 * or a specific variant (option keyword '[code]variant_xyz[/code]'), they are used instead default values.
 *
 * @author Bernard Paques
 * @author GnapZ
 * @tester Ghjmora
 * @tester ThierryP
 * @reference
 * @license http://www.gnu.org/copyleft/lesser.txt GNU Lesser General Public License
 */

// common definitions and initial processing
include_once '../shared/global.php';

// look for the id
$id = NULL;
if(isset($_REQUEST['id']))
	$id = $_REQUEST['id'];
elseif(isset($context['arguments'][0]))
	$id = $context['arguments'][0];
$id = strip_tags($id);

// get the item from the database
$item =& Sections::get($id);

// get the related anchor, if any
$anchor = NULL;
if(isset($item['anchor']) && $item['anchor'])
	$anchor =& Anchors::get($item['anchor']);

// check network credentials, if any -- used by winamp and other media players
if($user = Users::authenticate())
	Surfer::empower($user['capability']);

// editors have associate-like capabilities
if(Surfer::is_empowered('M') && (isset($item['id']) && isset($user['id']) && (Sections::is_assigned($item['id'], $user['id']))) || (is_object($anchor) && $anchor->is_editable()))
	Surfer::empower('A');

// associates and editors are always authorized
if(Surfer::is_empowered())
	$permitted = TRUE;

// the anchor has to be viewable by this surfer
elseif(is_object($anchor) && !$anchor->is_viewable())
	$permitted = FALSE;

// access is restricted to authenticated member
elseif(isset($item['active']) && ($item['active'] == 'R') && Surfer::is_empowered('M'))
	$permitted = TRUE;

// public access is allowed
elseif(isset($item['active']) && ($item['active'] == 'Y'))
	$permitted = TRUE;

// the default is to disallow access
else
	$permitted = FALSE;

// load a skin, maybe with a variant
load_skin('sections', $anchor, isset($item['options']) ? $item['options'] : '');

// stop crawlers
if(Surfer::is_crawler()) {
	Safe::header('Status: 403 Forbidden', TRUE, 403);
	die(i18n::s('You are not allowed to perform this operation.'));
}

// path to this page
$context['path_bar'] = array( 'sections/' => i18n::s('Sections') );

// page title
$context['page_title'] = i18n::s('RSS feed');

// not found
if(!isset($item['id']) || !$item['id']) {
	Safe::header('Status: 404 Not Found', TRUE, 404);
	Skin::error(i18n::s('No item has the provided id.'));

// access denied
} elseif(!$permitted) {

	// give anonymous surfers a chance for HTTP authentication
	if(!Surfer::is_logged()) {
		Safe::header('WWW-Authenticate: Basic realm="'.utf8::to_iso8859($context['site_name']).'"');
		Safe::header('Status: 401 Unauthorized', TRUE, 401);
	}

	// permission denied to authenticated user
	Safe::header('Status: 403 Forbidden', TRUE, 403);
	Skin::error(i18n::s('You are not allowed to perform this operation.'));

// display the section
} else {

	// get the list from the cache, if possible
	$cache_id = 'sections/feed.php?id='.$item['id'].'#channel';
	if(!$text =& Cache::get($cache_id)) {

		// loads feeding parameters
		Safe::load('parameters/feeds.include.php');

		// set channel information
		$values = array();
		$values['channel'] = array();
		$values['channel']['title'] = $item['title'];
		$values['channel']['link'] = $context['url_to_home'].$context['url_to_root'].Sections::get_permalink($item);
		$values['channel']['description'] = $item['introduction'];

		// the image for this channel
		if(isset($context['powered_by_image']) && $context['powered_by_image'])
			$values['channel']['image'] = $context['url_to_home'].$context['url_to_root'].$context['powered_by_image'];

		// all anchors to consider
		$anchors = array('section:'.$item['id']);

		// first level of depth
		$topics =& Sections::get_children_of_anchor('section:'.$item['id'], 'main');
		$anchors = array_merge($anchors, $topics);

		// second level of depth
		if(count($topics) && (count($anchors) < 50)) {
			$topics =& Sections::get_children_of_anchor($topics, 'main');
			$anchors = array_merge($anchors, $topics);
		}

		// third level of depth
		if(count($topics) && (count($anchors) < 50)) {
			$topics =& Sections::get_children_of_anchor($topics, 'main');
			$anchors = array_merge($anchors, $topics);
		}

		// the list of newest pages
		$values['items'] =& Articles::list_for_anchor_by('edition', $anchors, 0, 20, 'feeds');

		// make a text
		include_once '../services/codec.php';
		include_once '../services/rss_codec.php';
		$result = rss_Codec::encode($values);
		$text = @$result[1];

		// save in cache for the next request
		Cache::put($cache_id, $text, 'articles');
	}

	//
	// transfer to the user agent
	//

	// handle the output correctly
	render_raw('text/xml; charset='.$context['charset']);

	// suggest a name on download
	if(!headers_sent()) {
		$file_name = utf8::to_ascii($context['site_name'].'.section.'.$item['id'].'.rss.xml');
		Safe::header('Content-Disposition: inline; filename="'.$file_name.'"');
	}

	// enable 30-minute caching (30*60 = 1800), even through https, to help IE6 on download
	if(!headers_sent()) {
		Safe::header('Expires: '.gmdate("D, d M Y H:i:s", time() + 1800).' GMT');
		Safe::header("Cache-Control: max-age=1800, public");
		Safe::header("Pragma: ");
	}

	// strong validation
	if((!isset($context['without_http_cache']) || ($context['without_http_cache'] != 'Y')) && !headers_sent()) {

		// generate some strong validator
		$etag = '"'.md5($text).'"';
		Safe::header('ETag: '.$etag);

		// validate the content if hash is ok
		if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && is_array($if_none_match = explode(',', str_replace('\"', '"', $_SERVER['HTTP_IF_NONE_MATCH'])))) {
			foreach($if_none_match as $target) {
				if(trim($target) == $etag) {
					Safe::header('Status: 304 Not Modified', TRUE, 304);
					return;
				}
			}
		}
	}

	// actual transmission except on a HEAD request
	if(isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] != 'HEAD'))
		echo $text;

	// the post-processing hook, then exit
	finalize_page(TRUE);

}

// render the skin
render_skin();

?>