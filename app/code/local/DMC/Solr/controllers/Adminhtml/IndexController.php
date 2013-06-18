<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
class DMC_Solr_Adminhtml_IndexController extends Mage_Core_Controller_Front_Action
{
    public function incindexAction()
    {
        $solr = Mage::helper('solr')->getSolr();

        $i = 0;
        $ready = true;

        $class = $this->getRequest()->getParam('class');
        $lastId = $this->getRequest()->getParam('lastid');
        $storeId = $this->getRequest()->getParam('storeid');

        $adapter = new $class();
        $items = $adapter->getSourceCollection();
        $entityId = null;

        foreach($items as $item)
        {
            $entityId = $item->getEntity_id();
            if ($entityId <= $lastId )
                continue;

            $doc = $adapter->getSolrDocument();
            if($doc->setObject($item)) {
                $doc->setStoreId($storeId);
                if(!$solr->addDocument($doc)) {
                    $solr->deleteDocument($doc);
                }
                $i++;
            }
            else {
                //echo $item->id.' !! ';
            }
            if ($i == Mage::getStoreConfig(DMC_Solr_Model_Indexer::PRODUCTS_BY_PERIOD) ) {
                $solr->addDocuments();

                file_put_contents( Mage::getBaseDir('base') .'/solrLastId.mem',
                    $entityId );

                $ready = false;
                break;
            }
        }

        if ( $ready )
        {
            file_put_contents( Mage::getBaseDir('base') .'/solrLastId.mem', 'complete');
            $solr->addDocuments();
            $solr->commit();
        }
    }

    public function pingAction()
	{
		$solr = Mage::helper('solr')->getSolr();
		if($solr) {
			$responce = $solr->ping();
			echo $solr->getLastPingMessage();
		}
		else {
			echo '<font color="red">'.Mage::helper('solr')->__('Solr is unavailable, please check connection settings and solr server status').'</font>';
		}
	}
	
	public function clearAction()
	{
		$solr = Mage::helper('solr')->getSolr();
		if($solr) {
			$responce = $solr->deleteDocuments();
			if($responce->getHttpStatus() == '200') {
				echo '<font color="green">'.Mage::helper('solr')->__('Solr indexes are cleared').'</font>';
			}
		}
		else {
			echo '<font color="red">'.Mage::helper('solr')->__('Solr is unavailable, please check connection settings and solr server status').'</font>';
		}
	}
}
?>