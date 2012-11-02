<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
 
class DMC_Solr_Block_Autocomplete extends Mage_Core_Block_Abstract
{
	const COUNT_CATEGORIES = 5;
	
	protected $_collection = null;
	
	protected function _getQueryText()
	{
		return Mage::helper('catalogsearch')->getQuery()->getQueryText();
	}
	
	protected function _toHtml()
	{
		$html = '';
		
		if (!$this->_beforeToHtml()) {
			return $html;
		}
		
		if(is_null($this->_collection)) {
			$this->_collection = $this->_getProductCollection();
		}
		
		$data = array();
		
		$products = $this->_getProducts();
		$categories = $this->_getCategories();
		$pages = $this->_getPages();
		
		$data['category']['data'] = $categories;
		$data['category']['name'] = $this->__('Category');
		$data['products']['data'] = $products;
		$data['products']['name'] = $this->__('Products');
		$data['pages']['data'] = $pages;
		$data['pages']['name'] = $this->__('Pages');
		
		$html = '<table class="search-autocomplete">';
		foreach ($data as $sectionName => $section) {
			$html .=  '<tr class="'.$sectionName.'-wrapper">';
			if(count($section['data'])) {
				$html .=  '<th class="'.$sectionName.' section">'.$section['name'].'</th>';
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
		
		return $html;
	}

	protected function _getProducts()
	{
		$data = array();
		$counter = 0;
		foreach ($this->_collection as $item) {
			$_data = array(
				'name' => $item->getName(),
				'url' => $item->getProductUrl(),
				'thumb' => Mage::helper("catalog/image")->init($item, "small_image")->resize(35)
			);
		$data[] = $_data;
		}
		
		return $data;
	}
	
	protected function _getCategories()
	{
		$stat = $this->_collection->getStatistic();
		$counter = 0;
		$data = array();
		foreach ($stat['available_category_ids'] as $id => $count) {
			$i = 0;
			$category = Mage::getModel('catalog/category')->load($id);
			$_data = array(
				'name' => $category->getName(),
				'url' => $category->getUrl(),
				'thumb' => ''
			);
			if(($category->level == 2) && ($count > 0) && ($i <= self::COUNT_CATEGORIES)) {
				$data[] = $_data;
				$i++;
			}
		}
		
		return $data;
	}
	
	protected function _getPages()
	{
		$data = array();
		$counter = 0;
		$collection = Mage::getModel('DMC_Solr_Model_SolrServer_Adapter_Cms_Collection');
		$collection->addStoreFilter();
		$collection->addSearchFilter($this->_getQueryText());
		foreach($collection as $item) {
			$item->load($item->getId());
			$_data = array(
				'name' => $item->getTitle(),
				'url' => Mage::helper('cms/page')->getPageUrl($item->getId()),
				'thumb' => ''
			);
			$data[] = $_data;
		}
		
		return $data;
	}
	
	protected function _getProductCollection()
	{
		$collection = Mage::getModel('DMC_Solr_Model_SolrServer_Adapter_Product_Collection');
		$collection->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
			->addSearchFilter($this->_getQueryText())
			->setStore(Mage::app()->getStore())
			->addStoreFilter()
			->addUrlRewrite();
		$select = $collection->getSelect();
		$typeConverter = new DMC_Solr_Model_SolrServer_Adapter_Product_TypeConverter('select');
		$solrField = $typeConverter->solr_index_prefix.'index_'.'art';
		$select->param('facet', 'true', true);
		$select->param('facet.field', $solrField, true);
		$collection->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds());
		return $collection;
	}
/*
 *
*/
}
