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