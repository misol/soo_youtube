<?php
	/**
	 * @class	soo_youtube
	 * @author misol(김민수) <misol.kr@gmail.com>
	 * @brief	비디오 검색 컴포넌트
	 **/
class soo_youtube extends EditorHandler {
	var $editor_sequence = '0';
	var $component_path = '';

	function soo_youtube($editor_sequence, $component_path) {
		$this->editor_sequence = $editor_sequence;
		$this->component_path = $component_path;
	}

	function xml_api_request($uri, $headers = null) {
		$rss = '';
		$rss = FileHandler::getRemoteResource($uri, null, 3, 'GET', 'application/xml', $headers);

		$rss = preg_replace("/\<\?xml[.^>]+\?\>/i", "", $rss);

		$oXmlParser = new XmlParser();
		$xml_doc = $oXmlParser->parse($rss);

		return $xml_doc;
	}

	function json_api_request($uri, $headers = null) {
		$uri = str_replace('https://', 'ssl://', $uri);
		$json = '';
		$json = FileHandler::getRemoteResource($uri, null, 5, 'GET', null, $headers);

		$json_doc = json_decode($json);

		return $json_doc;
	}

	function page_calculator($total_result_no, $soo_result_start, $soo_search_display, $soo_display_set) {
		$obj = new Object();

		if($total_result_no >= $soo_result_start+$soo_search_display) $soo_next_page=$soo_result_start+$soo_display_set;
		else $soo_next_page="1";

		if($soo_result_start!='1')	$soo_before_page=$soo_result_start-$soo_display_set;
		else $soo_before_page="1";

		$obj->soo_next_page = $soo_next_page;
		$obj->soo_before_page = $soo_before_page;

		return $obj;
	}

	function soo_search() {
		$soo_display_set=trim($this->soo_display);
		$q_site = trim(Context::get('q_site'));
		$q_sort = urlencode(trim(Context::get('q_sort')));
		$query = urlencode(trim(Context::get('query')));
		$soo_result_start = urlencode(Context::get('soo_result_start'));

		if($q_site == 'daum') return $this->soo_search_daum($query, $soo_display_set, $soo_result_start, $q_sort);
		elseif($q_site == 'youtube' || !$q_site) return $this->soo_search_youtube($query, $soo_display_set, $soo_result_start, $q_sort);
		else return new Object(-1, '::	Component Error	::'."\n".'Unexpected request.');
	}

	function soo_search_youtube($query, $soo_display_set = '20', $soo_result_start = '1', $q_sort = 'relevance') {
		if(!$soo_display_set) $soo_display_set = '20';
		if(!$soo_result_start) $soo_result_start = '1';
		if(!$q_sort) $q_sort = 'relevance';
		$pageno = intval($soo_result_start / $soo_display_set) + 1;
		$langtype = array(
			'ko' => 'ko-KR',
			'en' => 'en-US',
			'zh-CN' => 'zh-CN',
			'jp' => 'ja-JP',
			'es' => 'es-ES',
			'ru' => 'ru-RU',
			'fr' => 'fr-FR',
			'zh-TW' => 'zh-TW',
			'vi' => 'vi-VN',
			'mn' => 'mn-MN'
		);
		$country_code = array(
			'ko' => 'KR',
			'en' => 'US',
			'zh-CN' => 'CN',
			'jp' => 'JP',
			'es' => 'ES',
			'ru' => 'RU',
			'fr' => 'FR',
			'zh-TW' => 'TW',
			'vi' => 'VN',
			'mn' => 'MN'
		);
		
		$lang_code = array(
			'ko' => 'ko',
			'en' => 'en',
			'zh-CN' => 'zh',
			'jp' => 'ja',
			'es' => 'es',
			'ru' => 'ru',
			'fr' => 'fr',
			'zh-TW' => 'zh',
			'vi' => 'vi',
			'mn' => 'mn'
		);

		if(isset($langtype[Context::getLangType()])) $langtype = $langtype[Context::getLangType()];
		else $langtype = 'en';

		if(isset($country_code[Context::getLangType()])) $country_code = $country_code[Context::getLangType()];
		else $country_code = 'US';

		if(isset($lang_code[Context::getLangType()])) $lang_code = $lang_code[Context::getLangType()];
		else $lang_code = 'en';


		$headers = array(
				'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => $langtype,
				'Connection' => 'close'
			);

		// $uri = sprintf('https://gdata.youtube.com/feeds/api/videos?q=%s&lang=%s&start-index=%s&max-results=%s&orderby=%s&v=2&alt=rss',$query, $langtype, $soo_result_start, $soo_display_set, $q_sort);
		$uri = sprintf('https://www.googleapis.com/youtube/v3/search?part=id&q=%s&regionCode=%s&relevanceLanguage=%s&maxResults=%s&order=%s&type=video&videoEmbeddable=true',$query, $country_code, $lang_code, $soo_display_set, $q_sort);

		$xml_doc = $this->json_api_request($uri, $headers);

		$error_code = trim($xml_doc->errors->error->code->body);
		$error_message = trim($xml_doc->errors->error->internalreason->body);
		if($error_message) return new Object(-1, '::	Youtube API Error	::'."\n".$error_code."\n".$error_message);

		$total_result_no = trim($xml_doc->rss->channel->{'opensearch:totalresults'}->body);
		$soo_result_start = trim($xml_doc->rss->channel->{'opensearch:startindex'}->body);
		$soo_search_display = trim($xml_doc->rss->channel->{'opensearch:itemsperpage'}->body);

		$obj = $this->page_calculator($total_result_no, $soo_result_start, $soo_search_display, $soo_display_set);

		$soo_next_page = $obj->soo_next_page;
		$soo_before_page = $obj->soo_before_page;

		$soo_results = $xml_doc->rss->channel->item;
		if(!is_array($soo_results)) $soo_results = array($soo_results);

		$soo_results_count = count($soo_results);
		$soo_result_start_end = trim($soo_result_start.' - '.($soo_result_start+$soo_results_count-1));
		$soo_list = array();
		for($i=0;$i<$soo_results_count;$i++) {
			$item = $soo_results[$i];
			$item_images = $item->{'media:group'}->{'media:thumbnail'};
			if(!is_array($item_images)) $item_images = array($item_images);
			$item_published = explode('T',$item->{'media:group'}->{'yt:uploaded'}->body);
			$item_updated = explode('T',$item->{'atom:updated'}->body);
			$item_second = ($item->{'media:group'}->{'yt:duration'}->attrs->seconds%60);
			$item_minute = (intval($item->{'media:group'}->{'yt:duration'}->attrs->seconds/60))%60;
			$item_hour =	intval((intval($item->{'media:group'}->{'yt:duration'}->attrs->seconds/60))/60);
			if(!is_array($item->{'media:group'}->{'media:content'})) $item->{'media:group'}->{'media:content'} = array($item->{'media:group'}->{'media:content'});

			$soo_list[] = sprintf("%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s",
				trim(strip_tags($item->title->body)),
				trim($item->author->body),
				trim(str_replace('http://www.youtube.com/v/','http://www.youtube.com/embed/',$item->{'media:group'}->{'media:content'}[0]->attrs->url)),
				trim($item_images[0]->attrs->url),
				trim($item_published[0]),
				trim($item_updated[0]),
				trim($item_hour),
				trim($item_minute),
				trim($item_second),
				trim($item->{'yt:statistics'}->attrs->viewcount),
				trim($item->link->body),
				cut_str(trim(strip_tags($item->title->body)),20)
				);
		}

		$this->add("total_result_no", $total_result_no);
		$this->add("soo_result_start", $soo_result_start);
		$this->add("soo_result_start_end", $soo_result_start_end);
		$this->add("result_list_bfpage", $soo_before_page);
		$this->add("result_list_nextpage", $soo_next_page);
		$this->add("result_list", implode("\n", $soo_list));
	}


	function soo_search_daum($query, $soo_display_set = '20', $soo_result_start = '1', $q_sort = 'accuracy') {
		$apikey = $this->soo_daum_api_key;

		if(!$soo_display_set) $soo_display_set = '20';
		if(!$soo_result_start) $soo_result_start = '1';
		if(!$apikey) return new Object(-1, ':: Component Error ::'."\n".'No Daum API KEY');
		$pageno = intval($soo_result_start / $soo_display_set) + 1;

		if($q_sort == 'published') $q_sort = 'recency';
		else $q_sort = 'accuracy';
		$uri = sprintf('http://apis.daum.net/search/vclip?q=%s&apikey=%s&pageno=%s&result=%s&sort=%s&output=xml',$query, $apikey, $pageno, $soo_display_set, $q_sort);

		$xml_doc = $this->xml_api_request($uri);

		$error_code = trim($xml_doc->apierror->code->body);
		$error_code .= ' | '.trim($xml_doc->apierror->dcode->body);
		$error_message = trim($xml_doc->apierror->message->body);
		$error_message .= "\n".trim($xml_doc->apierror->dmessage->body);
		if($xml_doc->apierror->code->body) return new Object(-1, ':: DAUM API Error ::'."\n".$error_code."\n".$error_message);

		$total_result_no = trim($xml_doc->channel->totalcount->body);
		$soo_search_display = trim($xml_doc->channel->result->body);

		$obj = $this->page_calculator($total_result_no, $soo_result_start, $soo_search_display, $soo_display_set);

		$soo_next_page = $obj->soo_next_page;
		$soo_before_page = $obj->soo_before_page;

		$soo_results = $xml_doc->channel->item;
		if(!is_array($soo_results)) $soo_results = array($soo_results);

		$soo_results_count = count($soo_results);
		$soo_result_start_end = trim($soo_result_start.' - '.($soo_result_start+$soo_results_count-1));
		$soo_list = array();
		for($i=0;$i<$soo_results_count;$i++) {
			$item = $soo_results[$i];
			$item_updated = explode('T',$item->{'atom:updated'}->body);
			$item_second = ($item->playtime->body % 60);
			$item_minute = (intval($item->playtime->body/60))%60;
			$item_hour =	intval((intval($item->playtime->body/60))/60);
			if(!is_array($item->{'media:group'}->{'media:content'})) $item->{'media:group'}->{'media:content'} = array($item->{'media:group'}->{'media:content'});

			$soo_list[] = sprintf("%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s,[[soo]],%s",
				trim(strip_tags($item->title->body)),
				trim($item->author->body),
				trim($item->player_url->body),
				trim($item->thumbnail->body),
				trim(date('Y-m-d',strtotime($item->pubdate->body))),
				trim($item_updated[0]),
				trim($item_hour),
				trim($item_minute),
				trim($item_second),
				trim($item->playcnt->body),
				trim($item->link->body),
				cut_str(trim(strip_tags($item->title->body)),20),
				trim($item->score->body),
				trim(strip_tags($item->tag->body)),
				trim($item->cpname->body)
				);
		}

		$this->add("total_result_no", $total_result_no);
		$this->add("soo_result_start", $soo_result_start);
		$this->add("soo_result_start_end", $soo_result_start_end);
		$this->add("result_list_bfpage", $soo_before_page);
		$this->add("result_list_nextpage", $soo_next_page);
		$this->add("result_list", implode("\n", $soo_list));
	}

/*
	function soo_search_vimeo($query, $soo_display_set = '20', $soo_result_start = '1', $q_sort = 'accuracy')
	{

		$headers = array(
			'Authorization' => 'OAuth realm="",oauth_consumer_key="e6e7e662087a8c624dd1192b289f469c",oauth_timestamp="'.time().'",oauth_nonce="'.md5(time()).'",oauth_signature_method="HMAC-SHA1",oauth_signature="'..'"'
		);
		$uri = 'http://vimeo.com/api/rest/v2?format=json&method=vimeo.videos.search&query=vimeo&sort=relevant&full_response=1&page=1&per_page=20';
		$output = FileHandler::getRemoteResource($uri, null, 3, 'GET', '', $headers);

	}
*/

	function getPopupContent() {
		$tpl_path = $this->component_path.'tpl';
		$tpl_file = 'popup.html';
		//if(!$this->soo_design) 
		$this->soo_design = 'misol';
		Context::set("tpl_path", $tpl_path);
		Context::set("design", htmlspecialchars(trim($this->soo_design)).'_popup.css');
		if(trim($this->soo_daum_api_key)) Context::set("soo_daum_api_key", trim($this->soo_daum_api_key));
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, $tpl_file);
	}

	function transHTML($obj) {
		$style = trim($obj->attrs->style).' ';
		preg_match('/width([ ]*):([ ]*)([0-9 a-z\.]+)(;| )/i', $style, $width_style);
		preg_match('/height([ ]*):([ ]*)([0-9 a-z\.]+)(;| )/i', $style, $height_style);
		if($width_style[3]) $width_style[3] = intval($width_style[3]);
		if($width_style[3]) $width = trim($width_style[3]);
		else $width = intval($obj->attrs->width);

		if($height_style[3]) $height_style[3] = intval($height_style[3]);
		if($height_style[3]) $height = trim($height_style[3]);
		else $height = intval($obj->attrs->height);
		$obj->attrs->value = trim($obj->attrs->value);
		$value_url = parse_url($obj->attrs->value);
		$src_url = parse_url($obj->attrs->src);
		if(!preg_match('/^(.+?)\.daum\.net$/m', $value_url['host']) && !preg_match('/^(.+?)\.youtube\.com$/m', $value_url['host'])) return 'Unexpected host error';
		$value = htmlspecialchars($obj->attrs->value);

		if(!$width) $width = 480;
		if(!$height) $height = 385;
		if(Mobile::isFromMobilePhone())
		{
			$hw_ratio = $height/$width;
			if($width > 300){
				$width = 300;
				$height = intval($hw_ratio*$width)+1;
			}
			if(preg_match('/^([\-\.a-z0-9]+?)\.daum\.net$/i', $value_url['host']))
			{
				preg_match('/vid\=([0-9a-z\$\_\-]+)$/i',$obj->attrs->value,$vid);
				$vid = $vid[1];
				if(preg_match('/^([\-\.a-z0-9]+)\.daum\.net$/i', $src_url['host']) || preg_match('/^([\-\.0-9A-Z]+)\.daumcdn\.net$/i', $src_url['host']))
					return sprintf('<a href="http://tvpot.daum.net/clip/ClipViewByVid.do?vid=%s" target="_blank"><img style="width:%dpx; height:%dpx;" src="%s" /></a>',$vid,$width,$height,$obj->attrs->src);
			}
			if(preg_match('/^([\-\.a-z0-9]+)\.youtube\.com$/i', $value_url['host']))
			{
				$value = str_replace(array('http://www.youtube.com/v/','http://'),array('http://www.youtube.com/embed/','https://'),$value);
				return sprintf('<iframe width="%d" height="%d" src="%s" frameborder="0" allowfullscreen></iframe>', $width, $height, $value);
			}
		}
		else
		{
			if(preg_match('/^([\-\.a-z0-9]+)\.daum\.net$/i', $value_url['host']))
				return sprintf('<object width="%d" height="%d"><param name="wmode" value="opaque"></param><param name="movie" value="%s"></param><param name="allowFullScreen" value="true"></param><embed width="%d" height="%d" src="%s" allowfullscreen="true" wmode="opaque"></embed></object>', $width, $height, $value, $width, $height, $value);
			if(preg_match('/^([\-\.a-z0-9]+)\.youtube\.com$/i', $value_url['host']))
			{
				$value = str_replace(array('http://www.youtube.com/v/','http://'),array('http://www.youtube.com/embed/','https://'),$value);
				return sprintf('<iframe width="%d" height="%d" src="%s" frameborder="0" allowfullscreen></iframe>', $width, $height, $value);
			}
		}
	}
}
?>