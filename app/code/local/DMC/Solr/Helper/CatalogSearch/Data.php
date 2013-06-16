<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
class DMC_Solr_Helper_CatalogSearch_Data extends Mage_CatalogSearch_Helper_Data
{
	public function getSuggestUrl()
	{
		return '/index.php?__quick&__controller=solr&__action=search';
	}
}
