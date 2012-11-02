<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
class DMC_Solr_Model_Catalog_Layer_Filter_Price extends Mage_Catalog_Model_Layer_Filter_Price
{
	public function getTypeConverter($inputType)
	{
		return new DMC_Solr_Model_SolrServer_Adapter_Product_TypeConverter($inputType);
	} 
	
    /**
     * Get maximum price from layer products set
     *
     * @return float
     */
    public function getMaxPriceInt()
    {
        $attribute = $this->getAttributeModel();
        $collection = $this->getLayer()->getProductCollection();
		$inputType = $attribute->getFrontend()->getInputType();
		$typeConverter = $this->getTypeConverter($inputType);
    	$fieldName = $typeConverter->solr_index_prefix.'index_'.$this->_requestVar;
        $maxPrice = $this->getData('max_price_int');
        if (is_null($maxPrice)) {

			if (get_class($this->getLayer()->getProductCollection()) === 'DMC_Solr_Model_SolrServer_Adapter_Product_Collection') {
				if(!is_null($this->_beforeApplySelect)) {
					$select = $this->_beforeApplySelect;
				}
				else {
					$select = $this->getLayer()->getProductCollection()->getSelect();
				}
            	$fselect = clone $select;
		    	$fselect->param('stats', 'true');
		    	$fselect->param('stats.field', $fieldName);
		    	$responce = Mage::helper('solr')->getSolr()->fetchAll($fselect);
		    	$priceStats = $responce->__get('stats')->stats_fields->$fieldName;
				//$price = $this->getLayer()->getProductCollection()->getPriceStats();
				if(is_object($priceStats)) $maxPrice = $priceStats->max;
				else $maxPrice = NULL;
	    	}
	    	else {
	            $maxPrice = $this->_getResource()->getMaxPrice($this);
	    	}
            $maxPrice = floor($maxPrice);
            $this->setData('max_price_int', $maxPrice);
        }
        return $maxPrice;
    }

    /**
     * Get information about products count in range
     *
     * @param   int $range
     * @return  int
     */
    public function getRangeItemCounts($range)
    {
        $attribute = $this->getAttributeModel();
        $collection = $this->getLayer()->getProductCollection();
		$inputType = $attribute->getFrontend()->getInputType();
		$typeConverter = $this->getTypeConverter($inputType);
    	$fieldName = $typeConverter->solr_index_prefix.'index_'.$this->_requestVar;
        $rangeKey = 'range_item_counts_' . $range;
        $items = $this->getData($rangeKey);
        if (is_null($items)) {
			if (get_class($this->getLayer()->getProductCollection()) === 'DMC_Solr_Model_SolrServer_Adapter_Product_Collection') {
				$items = array();
				if(!is_null($this->_beforeApplySelect)) {
					$select = $this->_beforeApplySelect;
				}
				else {
					$select = $this->getLayer()->getProductCollection()->getSelect();
				}
            	$fselect = clone $select;
            	$fselect->param('facet', 'true', true);
            	$fselect->param('facet.field', $fieldName, true);
            	$min = 0;
            	$max = $range-1;
                $maxPrice = $this->getMaxPriceInt();
            	//while($max<=$range*10) {
                while($min <= $maxPrice) {
                        $rangeQuery = $fieldName.':['.$min.' TO '.$max.'.99]';
            		$fselect->param('facet.query', $rangeQuery);
            		$min = $max+1;
            		$max = $max+$range;
            	}
            	$responce = Mage::helper('solr')->getSolr()->fetchAll($fselect);
				$valueArray = get_object_vars($responce->__get('facet_counts')->facet_queries);
				$int = 1;
				foreach($valueArray as $value) {
					if((int)$value !== 0) $items[$int] = $value;
					$int++;
				}
			}
			else {
	            $items = $this->_getResource()->getCount($this, $range);
	    	}
            $this->setData($rangeKey, $items);
        }
        return $items;
    }

    /**
     * Apply price range filter to collection
     *
     * @return Mage_Catalog_Model_Layer_Filter_Price
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {
        $filter = $request->getParam($this->getRequestVar());
                
        if (!$filter) {
            return $this;
        }
        
        $filterParams = explode(',', $filter);
        $interval = $this->_validateFilter($filterParams[0]);
        if (!$interval) {
            return $this;
        }
        $this->setInterval($interval);

        list($from, $to) = $interval;
        if ($to === '') {
            $to = $this->getMaxPriceInt();
            $to = (ceil($to/10))*10;
        } else {
            $to -= .01;
        }
        if ($from === '') $from = 0;

        $filterQuery = array();
        if (get_class($this->getLayer()->getProductCollection()) === 'DMC_Solr_Model_SolrServer_Adapter_Product_Collection') {
            $collection = $this->getLayer()->getProductCollection();
            $collection->addPriceData($this->getCustomerGroupId(), $this->getWebsiteId());
            $typeConverter = $this->getTypeConverter($this->getAttributeModel()->getFrontend()->getInputType());
            $fieldName = $typeConverter->solr_index_prefix.'index_'.$this->_requestVar;
            $rate = $this->getCurrencyRate();
            $filterQuery[] = $fieldName.':['.$from.' TO '.$to.']';
        } else {
            $range = $to - $from;
            $index = $to/$range;
            $this->_getResource()->applyFilterToCollection($this, $range, $index);
        }


        if (count($filterQuery)) {
            //$this->_beforeApplySelect = clone $collection->getSelect();
            $query = implode(' OR ', $filterQuery);
            //$this->saveBeforeApplySelect($this->getLayer()->getProductCollection()->getSelect());
            $collection->applyFilterToCollection($query);
        }

        $this->getLayer()->getState()->addFilter($this->_createItem(
            $this->_renderRangeLabel(empty($interval[0]) ? 0 : $interval[0], $interval[1]),
            $interval
        ));
        

        return $this;
    }
}
