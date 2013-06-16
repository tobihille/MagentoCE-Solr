<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
class DMC_Solr_IndexController extends Mage_Core_Controller_Front_Action
{
	public function indexAction()
	{
		$solr = Mage::helper('solr')->getSolr();
		$solr->delete();
	}

	public function livesearchAction()
	{
		if (!$this->getRequest()->getParam('q', false)) {
			$this->getResponse()->setRedirect(Mage::getSingleton('core/url')->getBaseUrl());
		}
		$this->getResponse()->setBody($this->getLayout()->createBlock('solr/autocomplete')->toHtml());
	}
}
?>