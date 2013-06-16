<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
 
class DMC_Solr_Model_Quick_Controller extends DMC_All_Model_Quick_Controller_Abstract
{
	const CATEGORY_ENTITY_TYPE_ID = 3;
	const CATEGORY_LEVEL = 2;
	const XML_PATH_RANGE_CALCULATION = 'catalog/layered_navigation/price_range_calculation';
	const XML_PATH_RANGE_STEP = 'catalog/layered_navigation/price_range_step';
	
	const RANGE_CALCULATION_AUTO    = 'auto';
	const RANGE_CALCULATION_MANUAL  = 'manual';
	const MIN_RANGE_POWER = 10;
        

        private $_priceRangeItemCounts = array();
	
	public function indexAction()
	{
		die('Executed search action');
	}
	
	public function searchAction()
	{
		$db = DMC_All_Model_Quick::app()->getDbConnection();
		$storeId = $db->getStoreIdByStoreCode(DMC_All_Model_Quick::app()->getStoreCode());
		if(!isset($_REQUEST['q']) || !strlen($_REQUEST['q'])) {
			throw new Exception('Search string not found');
		}
		$search = $_REQUEST['q'];
		$solrUrl = parse_url($db->getStoreConfigValue('solr/general/server_url'));
		$solr = new DMC_Solr_Model_Quick_Adapter($solrUrl['host'], $solrUrl['port'], $solrUrl['path']);
		
		$queryObject = new DMC_Solr_Model_SolrServer_Select();
		
		$q[] = 'attr_tg_search_name:'.$search.'*';
		$q[] = 'attr_tg_search_description:'.$search.'*';
		$q[] = 'attr_tg_search_short_description:'.$search.'*';
		
		$queryObject->where(implode(' OR ', $q));
		$queryObject->where('row_type:product');
		$queryObject->where('store_id:'.$storeId);
		$queryObject->param('facet', 'true');
		$queryObject->param('facet.field', 'available_category_ids');
		
		$queryPageObject = new DMC_Solr_Model_SolrServer_Select();
		
		$q = array();
		$q[] = 'attr_t_search_title:'.$search.'*';
		$q[] = 'attr_t_search_content_heading:'.$search.'*';
		$q[] = 'attr_t_search_content:'.$search.'*';
		
		$queryPageObject->where(implode(' OR ', $q));
		$queryPageObject->where('row_type:cms');
		$queryPageObject->where('store_id:'.$storeId);
		
		try {
			$dataObject = $solr->fetchAll($queryObject);
			$dataObject->setDocumentType('DMC_Solr_Model_SolrServer_Adapter_Product_Document');
			$data = $dataObject->__get('response');
			$facet = $dataObject->__get('facet_counts');
			$products = $this->_getProducts($data->docs);
			$prices = $this->_getPrices($data->docs, $queryObject, $solr, $search);
			$categories = $this->_getCategories($facet->facet_fields->available_category_ids, $storeId);
			
			$dataPageObject = $solr->fetchAll($queryPageObject);
			$dataPageObject->setDocumentType('DMC_Solr_Model_SolrServer_Adapter_Cms_Document');
			
			$data = $dataPageObject->__get('response');
			$pages = $this->_getPages($data->docs);
			
			$data = array();
			
			$data['category']['name'] =  'Kategorien';
			$data['category']['data'] = $categories;
			
			$data['products']['name'] =  'Produkte';
			$data['products']['data'] = $products;
			
			$data['prices']['name'] = 'Preise';
			$data['prices']['data'] = $prices;
			
			$data['pages']['name'] =  'Seiten';
			$data['pages']['data'] = $pages;
			
			$html = '<table class="search-autocomplete">';
			foreach ($data as $sectionName => $section) {
				$html .=  '<tr class="'.$sectionName.'-wrapper">';
				if(count($section['data'])) {
					$html .=  '<th class="'.$sectionName.' section"><span>'.$section['name'].'</span></th>';
					$html .=  '</tr><tr class="'.$sectionName.'-wrapper">';
					$html .=  '<td>';
						$html .= '<ul class="items">';
						foreach($section['data'] as $item) {
							if(strlen($item['thumb']))
								$img = '<img src="'.$item['thumb'].'">';
							else
								$img = '';
							$html .=  '<li>'.$img.'<a href="'.$item['url'].'">'.$item['name'].'</a></li>';
						}
						$html .= '</ul>';
					$html .= '</td>';
				}
				$html .=  '</tr>';
			}
			
			$html.= '</table>';
			
			echo $html;
		}
		catch(Exception $e) {
			var_dump($e);
		}
	}
	
	protected function _getProducts($docs)
	{
		$data = array();
		foreach ($docs as $doc) {
			$_data = array(
				'name' => $doc->attr_tg_search_name,
				'url' => DMC_All_Model_Quick::getBaseUrl('web').$doc->rewrite_path,
				'thumb' => DMC_All_Model_Quick::getBaseUrl('web').$doc->thumb,
			);
			$data[] = $_data;
		}
		return $data;
	}
	
	protected function _getCategories($docs, $storeId)
	{
		$data = array();
		$categories = array();
		$db = DMC_All_Model_Quick::app()->getDbConnection();
		
		foreach($docs as $id=>$count) {
			if($count) {
				$categories[$id] = $count;
			}
		}

		if( !empty($categories) ){
		
			$catstr = implode(',', array_keys($categories));
			$sql = 'SELECT *, cce.value as name
					FROM `catalog_category_entity` as cat
					LEFT JOIN `eav_attribute` as ea ON `ea`.`entity_type_id` ='.self::CATEGORY_ENTITY_TYPE_ID.' AND `ea`.`attribute_code`="name"
					LEFT JOIN `core_url_rewrite` as cur ON `cur`.`category_id` = `cat`.`entity_id` AND `cur`.`product_id` IS NULL
					LEFT JOIN `catalog_category_entity_varchar` as cce ON `cce`.`attribute_id` = `ea`.`attribute_id` AND `cat`.`entity_id` = `cce`.`entity_id`
					WHERE `cat`.`level`='.self::CATEGORY_LEVEL.' AND `cat`.`entity_id` IN ('.$catstr.') AND `cur`.`store_id` = '.$storeId.'
			';
			$result = $db->query($sql);

			while ($row = mysql_fetch_assoc($result)) {
				$_data = array(
					'name' => $row['name'],
					'url' => $row['request_path'],
					'thumb' => '',
				);
				$data[] = $_data;
			}

		}
		
		return $data;
	}
	
	protected function _getPages($docs)
	{
		$data = array();
		foreach ($docs as $doc) {
			$_data = array(
				'name' => $doc->attr_t_search_title,
				'url' => DMC_All_Model_Quick::getBaseUrl('web').$doc->url,
				'thumb' => '',
			);
			$data[] = $_data;
		}
		return $data;
	}
	
	protected function _getPrices($docs, $query, $solr, $search)
	{
		$maxPrice = null;
		$minPrice = null;
		foreach ($docs as $doc) {
			if(is_null($maxPrice)) {
				$maxPrice = $doc->attr_tg_search_price;
			}
			if(is_null($minPrice)) {
				$minPrice = $doc->attr_tg_search_price;
			}
			
			if($doc->attr_tg_search_price > $maxPrice) {
				$maxPrice = $doc->attr_tg_search_price;
			}
			if($doc->attr_tg_search_price < $minPrice) {
				$minPrice = $doc->attr_tg_search_price;
			}
			
			$prices[] = $doc->attr_tg_search_price;
		}
		$range = $this->_getPriceRange($minPrice, $maxPrice, $query, $solr);
		$items = $this->_getRangeItemCounts($maxPrice, $range, $query, $solr);
		$data = array();
		if($count = count($items)) {
                        $counter = 1;
			foreach($items as $index => $item) {
                                $startLink = ($index - 1)*$range;
                                $startLable = '€'.sprintf("%01.2f", $startLink);
                                if ($startLink == 0) {
                                    $startLink = '';
                                }
                                if ($counter < $count) {
                                    $endLink = $index*$range;
                                    $endLable = ' - '.'€'.sprintf("%01.2f", $endLink - .01);
                                } else {
                                    $endLable = ' and above';
                                    $endLink = '';
                                }
				$_data = array(
                                        'name' => $startLable.$endLable.' ('.$item.')',
                                        'url' => DMC_All_Model_Quick::getBaseUrl('web').'catalogsearch/result?q='.$search.'&price='.$startLink.'-'.$endLink,
					'thumb' => '',
				);
				$data[] = $_data;
                                $counter++;
                                
			}
		}
		return $data;
	}
	
	protected function _getPriceRange($minPrice, $maxPrice, $query, $solr)
	{
		$ranges = array();
		$calculation = DMC_All_Model_Quick::app()->getDbConnection()->getStoreConfigValue(self::XML_PATH_RANGE_CALCULATION);
		if(!$calculation)
			$calculation = self::RANGE_CALCULATION_AUTO;
			
		if ($calculation == self::RANGE_CALCULATION_AUTO) {
			$index = 1;
			do {
				$range = pow(10, (strlen(floor($maxPrice)) - $index));
				$items = $this->_getRangeItemCounts($maxPrice, $range, $query, $solr);
				$index++;
			}
			while($range > self::MIN_RANGE_POWER && count($items) < 2);
		}
		else {
			$range = DMC_All_Model_Quick::app()->getDbConnection()->getStoreConfigValue(self::XML_PATH_RANGE_STEP);
		}
		
		if(!$range)
			$range = 10;

		while (ceil($maxPrice / $range) > 25) {
			$range *= 10;
		}

		return $range;
	}

	protected function _getRangeItemCounts($maxPrice, $range, $query, $solr)
	{
            if (!isset($this->_priceRangeItemCounts[$range])) {
		$fieldName = 'attr_f_index_price';
		$items = array();
		$fselect = clone $query;
		$fselect->param('facet', 'true', true);
		$fselect->param('facet.field', $fieldName, true);
		$min = 0;
		$max = $range - 1;
                while($min <= $maxPrice) {
			$rangeQuery = $fieldName.':['.$min.' TO '.$max.'.99]';
			$fselect->param('facet.query', $rangeQuery);
			$min = $max+1;
			$max = $max+$range;
		}
		$responce = $solr->fetchAll($fselect);
		$responce->setDocumentType('DMC_Solr_Model_SolrServer_Adapter_Product_Document');
		$valueArray = get_object_vars($responce->__get('facet_counts')->facet_queries);
		$int = 1;
		foreach($valueArray as $value) {
			if((int)$value !== 0) $items[$int] = $value;
			$int++;
		}
                $this->_priceRangeItemCounts[$range] = $items;
            }
            return $this->_priceRangeItemCounts[$range];
	}
}
