<?php
// make sure that SimplePie has been already loaded
if (defined('ABSPATH') && defined('WPINC')) {
	require_once (ABSPATH . WPINC . '/class-simplepie.php');
}

class AmazonAutoLinks_Core_ {

	/*
		Todo: 
			- investigate the memory usage getting large in loops.
	*/
	
	/* Properties */
	public $classver = 'standard';
	public $feed = '';
	protected $pluginname = 'Amazon Auto Links';
    protected $pageslug = 'amazon-auto-links';
    protected $textdomain = 'amazon-auto-links';
	protected $oOption = array();
	protected $arrUnitOptions = array();
	public $arrASINs = array();	// stores a temporary ASIN data with the key of the product url	// used by Feed API as well so it must be public
	protected $strCharEncoding = '';	// stores the character encoding that the site uses.
	
	
	function __construct( &$arrUnitOptionsOrstrUnitLabel, &$arrGeneralOptions='') {
	
		// check the parameter
		if (empty($arrUnitOptionsOrstrUnitLabel)) {
			echo $this->pluginname . ": " . __METHOD__ . ": " . __('The first parameter cannot be empty.', 'amazon-auto-links') . '<br />';
			return;								
		}	
		
		// store the character encoding of the site. e.g. UTF-8
		$this->strCharEncoding = get_bloginfo( 'charset' ); 
		
		// classes
		$this->feed = new AmazonAutoLinks_SimplePie();		// this means class-simplepie.php must be included prior to instantiating this class
		$this->oAALfuncs = new AmazonAutoLinks_Helper_Functions( AMAZONAUTOLINKSKEY );
	
		// Setup Caches
		$this->feed->enable_cache( true );
		$this->feed->set_cache_class( 'WP_Feed_Cache' );
		$this->feed->set_file_class( 'WP_SimplePie_File' );
		$this->feed->enable_order_by_date( true );			// Making sure that it works with the defult setting. This does not affect the sortorder set by the option, $option['sortorder']

		// options
		$this->oOption = new AmazonAutoLinks_Options( AMAZONAUTOLINKSKEY );		
		if (is_array($arrUnitOptionsOrstrUnitLabel)) 	// unit option is directly passed
			$this->arrUnitOptions = $arrUnitOptionsOrstrUnitLabel;
		else {	// a unit label is passed, so retrieve the unit ID and store the unit options of the ID
			$strUnitLabel = $arrUnitOptionsOrstrUnitLabel;
			// for backward compatibility for the versions which used a unit label for the option key, v1.0.6 or ealier
			if ( isset( $this->oOption->arrOptions['units'][$strUnitLabel] ) && is_array( $this->oOption->arrOptions['units'][$strUnitLabel] ) )	{	
				$this->arrUnitOptions = $this->oOption->arrOptions['units'][$strUnitLabel];
			} else {
				$strUnitID = $this->oOption->get_unitid_from_unitlabel($strUnitLabel);
				if (empty($strUnitID)) {
					echo $this->pluginname . ": " . __METHOD__ . ": " . __('failed to retrieve the unit ID in the class constructor.' . ': ' . $strUnitLabel . '<br />', 'amazon-auto-links');
					return;								
				}
				$this->arrUnitOptions = $this->oOption->arrOptions['units'][$strUnitID];
			}
		}
		$this->arrUnitOptions = $this->arrUnitOptions + $this->oOption->GetDefaultUnitOptionKeys();	// this prevents undefined index warnings.
		
		$this->arrGeneralOptions = $arrGeneralOptions ? $arrGeneralOptions : $this->oOption->arrOptions['general'];
		
	}
	function get_unitid_from_unitlabel($strUnitLabel) {
		
		// since v1.0.7, retrieves the unit option id from the given unit label.
		// same as the method defined in AmazonAutoLinks_Options_ 
		// this is called from where the option class should not be instanciated to avoid overload
		return $this->oOption->get_unitid_from_unitlabel($strUnitLabel);
	}
	function cache_rebuild() {
		
		// since v1.0.5
		$arrLinks = $this->UrlsFromUnitLabel();
		$urls = $this->set_urls($arrLinks);
		$this->set_feed($urls, 0);	// set 0 for the second parameter to rebuild the caches. SimplePie compares the cache modified date with the current time + this value, then it renews the cache.
		
	}
	/* Method Fetch */
    function fetch( $arrRssUrls='' ) {
		
		// Verify parameters
		if ( empty( $this->arrUnitOptions['unitlabel'] ) ) {
			echo $this->pluginname . ": " . __METHOD__ . ": " . __( 'failed to retrieve the unit label.', 'amazon-auto-links' );
			return;
		}
	
		if ( $arrRssUrls =='' ) $arrRssUrls =  $this->UrlsFromUnitLabel();

		if ( count( $arrRssUrls ) == 0 ) {
			echo $this->pluginname . ": " . __METHOD__ . ": " . __( 'could not retrieve the urls for this unit.', 'amazon-auto-links' );
			return;
		}
		// if (!(is_array($arrRssUrls) && is_array($this->arrUnitOptions))) {
			// echo $this->pluginname . ": " . __('the plugin expects the option to be an array', 'amazon-auto-links');
			// return;
		// }
		
		/* Used Options 
			$this->arrUnitOptions['adtypes']
			$this->arrUnitOptions['associateid']
			$this->arrUnitOptions['itemlimit']
			$this->arrUnitOptions['cacheexpiration']	// for cache rebuild scheduling
			$this->arrUnitOptions['unitlabel']			// for cache rebuild scheduling
			$this->arrUnitOptions['IsPreview']			// for cache rebuild scheduling
			$this->arrUnitOptions['numitems']
			$this->arrUnitOptions['country']
			$this->arrUnitOptions['imagesize']
			$this->arrUnitOptions['nosim']
			$this->arrUnitOptions['itemformat']
			$this->arrUnitOptions['sortorder']
			$this->arrUnitOptions['containerformat']
			$this->arrUnitOptions['titlelength']
			$this->arrUnitOptions['disableonhome']		// since v1.2.0 disable option
			$this->arrUnitOptions['poststobedisabled']	// since v1.2.0 disable option
			$this->arrUnitOptions['blacklist_categories']	// since v1.2.2 blacklist categories
		*/
	
// echo '<pre>Unit Label: ' . print_r( $this->arrUnitOptions['unitlabel'], true ) . '</pre>';		
// echo '<pre>' . print_r( $this->arrUnitOptions['disableonhome'], true ) . '</pre>';		
// echo '<pre>is_home: ' . print_r( is_home(), true ) . '</pre>';		
// echo '<pre>' . print_r( $this->arrUnitOptions['disableonfront'], true ) . '</pre>';		
// echo '<pre>is_front_page: ' . print_r( is_front_page(), true ) . '</pre>';			
	
		// Do not continue if the disable option for pages is set.
		if ( $this->IsInDisabledPage() ) return;
		
		// first retrieve ASINs of blasklist categories.
		// $arrBlackASINs = isset( $this->arrUnitOptions['blacklist_categories'] ) ? $this->GetBlackASINs( $this->arrUnitOptions['blacklist_categories'] ) : array();
		$arrBlackASINs = $this->GetBlackASINs();
		
		try {

			/* Setup urls */
			$urls = $this->set_urls( $arrRssUrls );
			
			/* Setup the SimplePie instance */
			// set the life time longer bacause the background-cache-renew-crawling-functionality has been implemented as of v1.0.5.
			$this->set_feed($urls, 999999999);	// set -1 for the expiration time for no expireation

			/* Prepare blacklis */
			$arrBlackASINs = array_merge( $arrBlackASINs, $this->blacklist('blacklist') );	// for checking duplicated items
			$arrBlackTitleStrings = $this->blacklist('blacklist_title');	// for checking duplicated items
			$arrBlackDescriptionStrings = $this->blacklist('blacklist_description');	// for checking duplicated items
			
			/* Fetch */
			$output = '';
			$this->i = 0;

			foreach ($this->feed->get_items(0, 0) as $item) {
	
				/* DOM Object for description */
				$dom = $this->load_dom_from_htmltext( $item->get_description() );

				/* Div Node */
				$nodeDiv = $dom->getElementsByTagName('div')->item(0);		// the first depth div tag. If SimplePie is used outside of WordPress it should be the second depth which contains the description including images
				if (!$nodeDiv) continue;		// sometimes this happens when unavailable feed is passed, such as Top Rated, which is not supported in some countries.
	
				/* Image */
				$strImgURL = $this->get_image($dom, $this->arrUnitOptions['imagesize']);
	
				/* Link (hyperlinked url) */  // + ref=nosim
				$strPermalink = $this->modify_url($item->get_permalink());

				/* ASIN - required for detecting duplicate items and for ref=nosim */
				$strASIN = $this->arrASINs[$strPermalink];		// $strASIN = $this->get_ASIN($strPermalink);
						
				// Cloak URL
				$strPermalink = $this->cloak_url($strPermalink);
						
				/* Remove Duplicates with ASIN -- $arrASINs should be merged with black list array prior to it */
				if ( in_array( $strASIN, $arrBlackASINs ) ) continue;	// if the parsing item has been already processed, skip it.
				array_push( $arrBlackASINs, $strASIN );				
			
				/* Title */
				$strTitle = $this->fix_title( $item->get_title() );
				if ( !$strTitle ) continue;		//occasionally this happens that an empty title is given. 					
				if ( $this->stripos_array( $strTitle, $arrBlackTitleStrings ) ) continue; // if a black word is contained, skip.
							
				/* Description (creates $htmldescription and $textdescription) */ 
				$this->removeNodeByTagAndClass($nodeDiv, 'span', 'riRssTitle');
	
				// $textdescription -- although $htmldescription has the same routine, the following <a> tag modification needs text description for the title attribute
				$textdescription = $this->get_textdescription( $nodeDiv );		// needs to be done before modifying links
				if ( $this->stripos_array( $textdescription, $arrBlackDescriptionStrings ) ) continue; // if a black word is contained, skip.
				
				// Modify links in descriptions -- sets attributes and inserts ref=nosim if the option is set
				$this->modify_links($nodeDiv, $strTitle . ': ' . $textdescription);

				// $htmldescription  - this needs to be done again since it's modified
				$htmldescription = $this->get_htmldescription($nodeDiv);
							
				// format image -- if the image size is set to 0, $strImgURL is empty.
				$strImgTag = $strImgURL ? $this->format_image(array($strPermalink, $strImgURL, $strTitle, $textdescription)) : "";

				// item format
				$output .= $this->format_item(array($strPermalink, $strTitle, $htmldescription, $textdescription, $strImgTag));
						
				// Max Number of Items 
				if (++$this->i >= $this->arrUnitOptions['numitems']) break;
						
			} 	
		} catch (Exception $e) { $this->i = 0; }
		
		// schedule a background cache renewal event
		if ( empty($this->arrUnitOptions['IsPreview']) ) $this->schedule_cache_rebuild();
		
		// end the function by returning the result
		return $this->format_output($output);
    }
	function SetupFeedObjectForBlacklist( $vURLs, $bUseCache=True ) {
		
		// %vURLs can be numerical index array or a single url
		$oFeed = new AmazonAutoLinks_SimplePie();		// this means class-simplepie.php must be included prior to instantiating this class
		$oFeed->enable_cache( true );
		$oFeed->set_cache_class( 'WP_Feed_Cache' );
		$oFeed->set_file_class( 'WP_SimplePie_File' );
		$oFeed->enable_order_by_date( false );
		$oFeed->set_feed_url( $vURLs );
		$nLifeTime = $bUseCache ? 999999999 : 0;
		$oFeed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', $nLifeTime, $vURLs ) );
		$oFeed->set_stupidly_fast( true );
		$oFeed->init();		// at this point, the cache will be careated.
		return $oFeed;
	}
	function GetBlackASINByFeedURL( $strFeedURL, $bUseTransients=True ) {
	
		// since v1.2.2
		// returns an array of ASINs to skip - singular
		// The transient name: aal_black_ + md5hash
		// The ASIN transient will be created per url. This is important to cover all products to block and prevent any oversight.
		// The second parameter can specify not to use transients. This will be used when it needs to rebuild the caches.
		// The cache renewal event will be triggered together with the schedule_cache_rebuild() method.
		// if the url is not set, return an empty array.
		if ( ! $strFeedURL ) return array();
		
		// if the transient exists, returns the stored data.
		$arrASINs = $bUseTransients ? get_transient( 'aal_black_' . md5( $strFeedURL ) ) : false ;
		$this->oOption->oLog->Append( $strFeedURL . ': ' . print_r( $arrASINs, true ) , __METHOD__ );				
		if ( false !== $arrASINs ) return $arrASINs; 
		
		// retrieve the ASINs
		// set_transient( 'special_query_results', $special_query_results );
		$arrASINs = array();
		$oFeed = $this->SetupFeedObjectForBlacklist( $strFeedURL, $bUseTransients );
		foreach ( $oFeed->get_items( 0, 0 ) as $item ) {	// ( 0, 0 ) means parse all elements

			// modify_url() internally stores ASINs in arrASINs
			$strPermalink = $this->modify_url( $item->get_permalink() );		
			if ( $strASIN = $this->arrASINs[$strPermalink] )	
				$arrASINs[] = $strASIN;
				
		}
		set_transient( 'aal_black_' . md5( $strFeedURL ), $arrASINs, 999999999 );
		$this->oOption->oLog->Append( 'The black category\'s ASINs are saved in the transient: ' . 'aal_black_' . md5( $strFeedURL ) . ' ' . $strFeedURL, __METHOD__ );
		return $arrASINs;
	}
	function GetBlackASINs( $bUseTransients=True ) {

		// since v1.2.2 
		// returns an array of ASINs to skip. - plural
		
		$arrBlackCats = isset( $this->arrUnitOptions['blacklist_categories'] ) ? $this->arrUnitOptions['blacklist_categories'] : array();
		
		// If the key is not set or not added a category to block, return an empty array. 
		if ( count( $arrBlackCats ) == 0 ) return array();

		$arrBlackASINs = array();		
		$arrFeedURLs = $this->GetFeedUrlsFromCategories( $arrBlackCats );
		foreach ( $arrFeedURLs as $strFeedURL ) 
			$arrBlackASINs = array_merge( $arrBlackASINs, $this->GetBlackASINByFeedURL( $strFeedURL, $bUseTransients ) );

		return $arrBlackASINs;
	}
	function GetFeedUrlsFromCategories( $arrCats ) {
		// since v1.2.2 
		/*	
		 *  The passed category array must be formatted to the plugin specific option array,
		 *  defined in the admin_tab_selectcategories() method in the AmazonAutoLinks_Admin class.
			array( 
				// numeric index
				array(
					// key: category breadcrumb
					// value: array of page url and feed url
					{category name} => array(
						'pageurl' => {url}
						'feedurl' => {url}
					)
				)
				array(
					... repeats ...
				)
			)
		* */ 
		$arrLinks = array();
		foreach( $arrCats as $catname => $catinfo ) 
			$arrLinks = array_merge( $arrLinks, $this->GetOtherTypeFeedURLs( $catinfo['feedurl'] ) );
		
		return $arrLinks;
	}	
	function GetOtherTypeFeedURLs( $arrRssUrls ) {
		// since v1.2.2
		// retrieves the feed url of all types
		$arrURLs = array();
		foreach ( ( array ) $arrRssUrls as $i => $strRssUrl ) {	
			foreach ( $this->arrUnitOptions['adtypes'] as $adtype ) {		// it is assumed that this class is instanciated per a unit
				// http://www.amazon.co.jp/gp/rss/bestsellers/sports/ -> http://www.amazon.co.jp/gp/rss/bestsellers/sports/?tag=michaeluno-22
				array_push( $arrURLs, str_replace( "/gp/rss/bestsellers/", "/gp/rss/" . $adtype['slug'] . "/", $strRssUrl ) );	
			}	
		}
		return $arrURLs;
	}	
	function IsInDisabledPage() {
		// since v1.2.2 - moved from fetch()
		global $wp_query;
		if ( isset( $this->arrUnitOptions['disableonhome'] ) && !empty( $this->arrUnitOptions['disableonhome'] ) && ( is_home() || is_front_page() ) ) return True;	// since v1.2.0
		$arrPostIDsToBeDisabled = preg_split( '/\s?[,]\s?+/', isset( $this->arrUnitOptions['poststobedisabled'] ) ? $this->arrUnitOptions['poststobedisabled'] : '', -1, PREG_SPLIT_NO_EMPTY );
		if ( is_object( $wp_query->post ) && in_array( $wp_query->post->ID, $arrPostIDsToBeDisabled ) )	return True;
	}	
	function schedule_cache_rebuild() {
	
		// since v1.0.5
		if ( !$this->arrUnitOptions['unitlabel'] ) return;	// if the option has no unit label, it's a previw unit, so do nothing
		$numSceduledTime = wp_next_scheduled( 'aal_feed_' . md5( $this->arrUnitOptions['unitlabel'] ) );
		$bIsScheduled = !empty( $numSceduledTime );
		$numExpirationTime = time() + $this->arrUnitOptions['cacheexpiration'];
		if ( $bIsScheduled && ( $numSceduledTime < $numExpirationTime ) )	// already scheduled
		{
			$this->oOption->oLog->Append( '"' . $this->arrUnitOptions['unitlabel'] . '" is already scheduled. Returning. $numSceduledTime: ' . date('Y m d h:i:s A', $numSceduledTime) . ' $numExpirationTime: ' . date('Y m d h:i:s A', $numExpirationTime) );
			return;	//  if the event has been already scheduled, do nothing
		}
		
		// instanciate the event object
		// the class needs the option object
		$oAALEvents = new AmazonAutoLinks_Events( $this->oOption );	
		
		if ( !$bIsScheduled )		// means there is no schedule for this unit to renew its cache
			$oAALEvents->schedule_feed_cache_rebuild( $this->arrUnitOptions['unitlabel'], 0 );	// the second parameter means do it in the next page load
		else 	//if ($numSceduledTime > time() + $this->arrUnitOptions['cacheexpiration'])		// this means that the scheduled time is set incorrectly; in other words, the cache expiration option has been changed by the user.
			$oAALEvents->reschedule_feed_cache_rebuild( $numSceduledTime, $this->arrUnitOptions['unitlabel'], $this->arrUnitOptions['cacheexpiration'] );	// delete the previous schedule and add a new schedule
			
	}
	function set_urls( $arrRssUrls ) {
		$arrURLs = array();
		foreach ( ( array ) $arrRssUrls as $i => $strRssUrl ) {	
			foreach ($this->arrUnitOptions['adtypes'] as $adtype) {		// it is assumed that this class is instanciated per a unit
				if ($adtype['check']) {
					// http://www.amazon.co.jp/gp/rss/bestsellers/sports/ -> http://www.amazon.co.jp/gp/rss/bestsellers/sports/?tag=michaeluno-22
					array_push($arrURLs, str_replace("/gp/rss/bestsellers/", "/gp/rss/" . $adtype['slug'] . "/", $strRssUrl . '?tag=' . $this->arrUnitOptions['associateid'] ));
				}
			}	
		}
		$numRssUrls = count($arrRssUrls);
		if ($numRssUrls == 0) throw new Exception("");	// get out of there
						
		// set the `itemlimit` option
		$this->arrUnitOptions['itemlimit'] = ceil($this->arrUnitOptions['numitems'] / $numRssUrls);
		return $arrURLs;
	}	
	function blacklist( $strOptionFieldName='blacklist' ) {
		return preg_split( '/\s?[,]\s?+/', $this->arrGeneralOptions[$strOptionFieldName], -1, PREG_SPLIT_NO_EMPTY );	// returns the array
	}
	function stripos_array( $haystack, $arrNeedles=array(), $offset=0 ) {
		// since v1.1.6
// print '<pre>' . print_r($arrNeedles, true) . '</pre>';
        foreach( $arrNeedles as $needle ) if ( stripos( $haystack, trim( $needle ), $offset ) !== false ) return true;
		return false;        
	}
	function set_feed( $urls, $numLifetime, $oFeed='' ) {
	
		$oFeed = empty( $oFeed ) ? $this->feed : $oFeed;
		
		// Set Sort Order
		$oFeed->set_sortorder( $this->arrUnitOptions['sortorder'] );
		$oFeed->set_charset_for_sort( $this->strCharEncoding );
		
		// Set title state
		$oFeed->set_keeprawtitle( $this->arrUnitOptions['keeprawtitle'] );
		
		// Set urls
		$oFeed->set_feed_url($urls);
		
		// Set the number of items to display per feed
		if ( isset( $this->arrUnitOptions['itemlimit'] ) ) 
			$oFeed->set_item_limit( $this->arrUnitOptions['itemlimit'] );
					
		// this should be set after defineing $urls
		$oFeed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', $numLifetime, $urls ) );
	
		$oFeed->set_stupidly_fast( true );
		$oFeed->init();
			
		// Character Encodings etc.
		// $this->feed->handle_content_type();		// <-- this breaks XML validation when the feed items are fetched and displayed as XML such as used in the the_content_feed filter.			
	}	
	function load_dom_from_htmltext( $rawdescription, $lang='' ) {
		// $dom = new DOMDocument();		// $dom = new DOMDocument('1.0', 'utf-8');
		$dom = new DOMDocument( '1.0', $this->strCharEncoding );
// echo 'test: ' . $this->strCharEncoding . '<br />';		
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
			// mb_language( $lang ); // <-- without this, the characters get broken
// $description = @mb_convert_encoding( $rawdescription, 'HTML-ENTITIES', $strDetectedEncoding );	

			// $description = @mb_convert_encoding( $rawdescription, 'HTML-ENTITIES', 'AUTO' );	
		$strDetectedEncoding =  @mb_detect_encoding( $rawdescription, 'AUTO' );	
		$description = @mb_convert_encoding( $rawdescription, $this->strCharEncoding , $strDetectedEncoding );	
		$description = @mb_convert_encoding( $description, 'HTML-ENTITIES', $this->strCharEncoding ); 		
		$description = '<div>' . $description . '</div>';		// this prevents later when using saveXML() from inserting the comment <!-- xml version .... -->
		@$dom->loadhtml( $description );
		return $dom;
	}	
	function get_image($dom, $numImageSize) {
		$strImgURL =""; // this line is necessary since some item don't have images so the domnode cannot be retrieved.	
		if ($numImageSize > 0) {			
			$nodeImg = $dom->getElementsByTagName('img')->item(0);
			if ($nodeImg) {
				$strImgURL = $nodeImg->attributes->getNamedItem("src")->value;
				$strImgURL = preg_replace('/_SL(\d+){3}_/i', '_SL'. $numImageSize . '_', $strImgURL);  // adjust the image size. _SL160_
			} 
		}
		// removes the div tag containing the image
		foreach ( $dom->getElementsByTagName( 'div' ) as $nodeDivFloat ) {
			if (stripos($nodeDivFloat->getAttribute('style'), 'float') !== false) {		// if the string 'float' is found 
				$nodeDivFloat->parentNode->removeChild($nodeDivFloat);
				break;
			}
		}
		return $strImgURL;
	}	
	function get_ASIN( $strURL )	{
		
		// retrieves and returns the ASIN, 10 characters which represents the product, from the given url/string
		
		// example regex patterns:
		// /http:\/\/(?:www\.|)amazon\.com\/(?:gp\/product|[^\/]+\/dp|dp)\/([^\/]+)/
		// "http://www.amazon.com/([\\w-]+/)?(dp|gp/product)/(\\w+/)?(\\w{10})"
	
		preg_match( '/(dp|gp|e)\/(.+\/)?(\w{10})(\/|$|\?)/i', $strURL, $matches );	// \w{10} is the ASIN
		return IsSet( $matches[3] ) ? $matches[3] : "";	// if not found, it returns an empty string

	}	
	function fix_title($strTitle) {
		$strTitle = strip_tags($strTitle);

		// removes the heading numbering. e.g. #3: Product Name -> Product Name
		// Do not use "substr($strTitle, strpos($strTitle, ' '))" since some title contains double-quotes and they mess up html formats
		if ( ! $this->arrUnitOptions['keeprawtitle'] )
			$strTitle = trim(preg_replace('/#\d+?:\s+?/i', '', $strTitle));
		
		// title character length	// since v1.0.7
		if (isset($this->arrUnitOptions['titlelength']) && $this->arrUnitOptions['titlelength'] >= 0) {
			if ($this->arrUnitOptions['titlelength'] == 0)
				$strTitle = '';
			else if (mb_strlen($strTitle) > $this->arrUnitOptions['titlelength'])
				$strTitle = mb_substr($strTitle, 0, $this->arrUnitOptions['titlelength']) . '...';
		}	
		return $strTitle;
	}
	function removeNodeByTagAndClass($node, $tagname, $className) {
	
		// remove the span tag containing the title
		$nodeSpanTitle = $node->getElementsByTagName($tagname)->item(0);
		if ($nodeSpanTitle) {		
			if (stripos($nodeSpanTitle->getAttribute('class'), $className) !== false) {		// if the string 'riRssTitle' is found 
				$nodeSpanTitle->parentNode->removeChild($nodeSpanTitle);
			}
		}	 
	}		
	function get_textdescription($node) {
		$arrDescription = preg_split('/<br.*?\/?>/i', $this->DOMInnerHTML( $node ) );		// devide the string into arrays by <br> or <br />
		array_splice($arrDescription, -2);		// remove the last two elements	
		$htmldescription = implode( "&nbsp;", $arrDescription );
		return html_entity_decode( trim( strip_tags( $htmldescription ) ), ENT_QUOTES, $this->strCharEncoding );
	}	
	function modify_links( $node, $strTitleAttribute ) {
		foreach ($node->getElementsByTagName( 'a' ) as $nodeA ) {
			$strHref = $nodeA->getAttribute( 'href' );
			if ( empty( $strHref ) ) continue;
			$strHref = $this->modify_url( $strHref );
			$strHref = $this->cloak_url( $strHref );		

			// Reported Issue: Warning: DOMElement::setAttribute() [domelement.setattribute]: string is not in UTF-8
			$bResult = @$nodeA->setAttribute( 'href', $strHref );		
			// if (empty($bResult)) echo "error setting the url: " . $strHref;
			@$nodeA->setAttribute( 'rel', 'nofollow' );
			@$nodeA->setAttribute( 'title', $strTitleAttribute );
		}
	}	
	function modify_url($strURL) {
		
		// link style since v1.0.8
		$numStyle = isset($this->arrUnitOptions['linkstyle']) ? $this->arrUnitOptions['linkstyle'] : 1;
		$strURL = $this->linkstyle($strURL, $numStyle);

		return $strURL;
	}
	function cloak_url($strURL) {
		
		// since v1.0.9
		if (!array_key_exists('urlcloak', $this->arrUnitOptions) || empty($this->arrUnitOptions['urlcloak'])) return $strURL ;	// v1.0.8 or below does not have this option value, so return				
		$strCloakQuery = empty($this->arrGeneralOptions['cloakquery']) ? $this->oOption->generaldefaultoptions['cloakquery'] : $this->arrGeneralOptions['cloakquery'];
		$strEncrypted = $this->oAALfuncs->urlencrypt($strURL);
		return site_url('?' . rawurlencode($strCloakQuery) . '=' . $strEncrypted);
		
	}
	function linkstyle($strURL, $numStyle) {

		// since v1.0.8 $numStyle should be 1 to 4 indicating the url style of the link		
		switch ($numStyle) {
			case 1: // http://www.amazon.[domain-suffix]/[product-name]/dp/[asin]/ref=[...]?tag=[associate-id]

				// ref=nosim
				if (!empty($this->arrUnitOptions['nosim'])) 
					$strURL = preg_replace('/ref\=(.+?)(\?|$)/i', 'ref=nosim$2', $strURL);		
				
				// tag
				$strTag = 'tag=' . $this->arrUnitOptions['associateid'];
				if (stripos($strURL, $strTag) === false) 	// if the associate id is not found, add it
					$strURL .= '?' . $strTag;			
					
				// tag replacement
				$strURL = $this->alter_tag_in_url_query($strURL);
				
				// store ASIN of this url
				$strASIN = $this->get_ASIN($strURL);	
				
				break;
				
			case 2: // http://www.amazon.[domain-suffix]/exec/obidos/ASIN/[asin]/[associate-id]/ref=[...]
				
				// create an array consisting of the url elements
				$arrURLelem = parse_url($strURL);

				// ref=nosim
				$strRefNosim = (empty($this->arrUnitOptions['nosim'])) ? '' : '/ref=nosim';
				
				// get ASIN
				$strASIN = $this->get_ASIN($arrURLelem['path']);
				$strURL = $arrURLelem['scheme'] . '://' . $arrURLelem['host'] . '/exec/obidos/ASIN/' . $strASIN . '/' . $this->alter_tag($this->arrUnitOptions['associateid']) . $strRefNosim;				
				
				break;
				
			case 3:	// http://www.amazon.[domain-suffix]/gp/product/[asin]/?tag=[associate-id]&ref=[...]

				// create an array consisting of the url elements
				$arrURLelem = parse_url($strURL);
			
				// ref=nosim
				$strRefNosim = (empty($this->arrUnitOptions['nosim'])) ? '' : '&ref=nosim';
				
				// get ASIN
				$strASIN = $this->get_ASIN($arrURLelem['path']);

				// modify the url
				$strURL = $arrURLelem['scheme'] . '://' . $arrURLelem['host'] . '/gp/product/' . $strASIN . '/?tag=' . $this->alter_tag($this->arrUnitOptions['associateid']) . $strRefNosim;

				break;
			case 4:	// http://www.amazon.[domain-suffix]/dp/ASIN/[asin]/ref=[...]?tag=[associate-id]

				// create an array consisting of the url elements
				$arrURLelem = parse_url($strURL);
				
				// ref=nosim
				$strRefNosim = (empty($this->arrUnitOptions['nosim'])) ? '' : 'ref=nosim';
				
				// store ASIN of this url - this would be used in the fetch() method to check black list items
				$strASIN = $this->get_ASIN($arrURLelem['path']);
		
				// modify the url
				$strURL = $arrURLelem['scheme'] . '://' . $arrURLelem['host'] . '/dp/ASIN/' . $strASIN . '/' . $strRefNosim . '?tag=' . $this->alter_tag($this->arrUnitOptions['associateid']);

				break;							
		}

		// store ASIN of modified url - this would be used in the fetch() method to check black list items
		$this->arrASINs[$strURL] = $strASIN;

		return $strURL;
	}
	function insert_ref_nosim($strURL)  {
		return preg_replace('/ref\=(.+?)(\?|$)/i', 'ref=nosim$2', $strURL);
	}
	function alter_tag_in_url_query($strURL) {
		if (isset($this->arrGeneralOptions['supportrate']) && $this->does_occur_in($this->arrGeneralOptions['supportrate'])) {
			$strToken = $this->oOption->get_token($this->arrUnitOptions['country']);
			$strURL = preg_replace('/(?<=tag=)(.+?-\d{2,})?/i', $strToken, $strURL);	// the pattern is replaced from '/tag\=\K(.+?-\d{2,})?/i' since \K is avaiable above PHP 5.2.4
		}
		return $strURL;
	}
	function alter_tag($strString) {
		if (isset($this->arrGeneralOptions['supportrate']) && $this->does_occur_in($this->arrGeneralOptions['supportrate'])) 
			return $this->oOption->get_token($this->arrUnitOptions['country']);
		return $strString;	
	}
	function does_occur_in($numPercentage) {
		if (mt_rand(1, 100) <= $numPercentage) return true;			
		return false;
	}		
	function get_htmldescription($node) {
	
		// Add markings to text node which later convert to a whitespace because by itself elements don't have white spaces between each other.
		foreach( $node->childNodes as $_node ) {
			if ($_node->nodeType == 3) {		// nodeType:3 TEXT_NODE
				$_node->nodeValue = '[identical_replacement_string]' . $_node->nodeValue . '[identical_replacement_string]';
			}
		}
		
		// AAL_DOMInnerHTML extracts intter html code, meaning the outer div tag won't be with it
		$strDescription = $this->DOMInnerHTML($node);
		$strDescription = str_replace('[identical_replacement_string]', '<br>', $strDescription);
		
		// omit the text 'visit blah blah blah for more information'
		if (preg_match('/<span.+class=["\']price["\'].+span>/i', $strDescription)) {
		
			// $arrDescription = preg_split('/<span.+class=["\']price["\'].+span>\K/i', $strDescription);  // this works above PHP v5.2.4
			$arrDescription = preg_split('/(<span.+class=["\']price["\'].+span>)\${0}/i', $strDescription, null, PREG_SPLIT_DELIM_CAPTURE);
			
		} else {
		
			// $arrDescription = preg_split('/<font.+color=["\']#990000["\'].+font>\K/i', $strDescription);	 // this works above PHP v5.2.4
			$arrDescription = preg_split('/(<font.+color=["\']#990000["\'].+font>)\${0}/i', $strDescription, null, PREG_SPLIT_DELIM_CAPTURE);	// " (syntax fixer )
		}	
		$strDescription1 = isset( $arrDescription[0] ) ? $arrDescription[0] : '';
		$strDescription2 = isset( $arrDescription[1] ) ? $arrDescription[1] : '';
		$strDescription = $strDescription1 . $strDescription2;
		$arrDescription = preg_split('/<br.*?\/?>/i', $strDescription);		// devide the string into arrays by <br> or <br />	
		return trim(implode(" ", $arrDescription));	// return them back to html text
	} 
	
	function format_image($arrReplacementsForImg) {
		$arrRefVarsForImg = array("%link%", "%imgurl%", "%title%", "%textdescription%");
		return str_replace($arrRefVarsForImg, $arrReplacementsForImg, $this->arrUnitOptions['imgformat']);
	}	
	function format_item($arrReplacements)	{
		if (count(array("%link%", "%imgurl%", "%title%", "%url%", "%title%", "%htmldescription%", "%textdescription%", "%img%", "%items%")) < $this->i ) throw new Exception("");
		$arrRefVars = array("%link%", "%title%", "%htmldescription%", "%textdescription%", "%img%");
		return str_replace($arrRefVars, $arrReplacements, $this->arrUnitOptions['itemformat']);
	}				
	function format_output($output) {
		$strCredit = empty($this->arrUnitOptions['credit']) ? '' : '<span> by <a href="http://en.michaeluno.jp/amazon-auto-links">Amazon Auto Links</a></span>';
		$strOut = str_replace("%items%", $output, $this->arrUnitOptions['containerformat'])
			. $strCredit
			. '<!-- generated by Amazon Auto Links powered by miunosoft. http://michaeluno.jp -->';
		return balanceTags( $strOut, true );	// added in v 1.1.5
	}	
	function UrlsFromUnitLabel() {
		$arrLinks = array();
		foreach($this->arrUnitOptions['categories'] as $catname => $catinfo) 
			array_push($arrLinks, $catinfo['feedurl']);
		return $arrLinks;
	}
	function DOMInnerHTML( $element ) {
		$innerHTML = ""; 
		$children = $element->childNodes; 
		foreach ($children as $child) { 
			// $tmp_dom = new DOMDocument(); 
			$tmp_dom = new DOMDocument( '1.0', $this->strCharEncoding );
			$tmp_dom->appendChild( $tmp_dom->importNode( $child, true ) ); 
			$innerHTML .= trim( @$tmp_dom->saveHTML() ); 
		} 
		return $innerHTML; 	
	}
	function formatvalidation($output, $i) {
		if (count(array("%link%", "%imgurl%", "%title%", "%link%", "%title%", "%htmldescription%", "%textdescription%", "%img%", "%items%")) < $i ) throw new Exception("");
	}		
	
	// for the Amazon Auto Links Feed API extention
	// since v1.1.8
	function output_rss() {

		do_action( 'aalhook_output_rss', $this, $this->arrUnitOptions );

	}
	function pick_category_link() {
		// moved from the pro version since v1.1.8 for Feed API
		// returns only one representative url from the stored category urls
		$arrCatLinks = $this->arrUnitOptions['categories'];
		shuffle($arrCatLinks);
		foreach($arrCatLinks as $catname => $catinfo) 
			return $catinfo['pageurl'] . '?tag=' . $this->arrUnitOptions['associateid'];
	}	
}
?>