<?php

namespace Abhinay\Epicor\Helper;

use Magento\Framework\Registry;
use Magento\Customer\Model\Session;

/**
 * Class Data
 * @package Abhinay\Epicor\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    const EPICOR_API_URL = 'epicor/general/api_url';

    const EPICOR_API_USER = 'epicor/general/epicor_user';

    const EPICOR_API_PASSWORD = 'epicor/general/epicor_password';

    const EPICOR_GUEST_ENTITY = 'epicor/general/epicor_guest_entity';

    const EPICOR_GUEST_HOME = 'epicor/general/epicor_guest_branch';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $_request;
    /**
     * @var Session
     */
    protected $session;

    /**
     * Data constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Session $customerSession
     * @param \Abhinay\Core\Logger\Logger $logger
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Session $customerSession,
        \Abhinay\Core\Logger\Logger $logger,
        array $data = []
    )
    {
        $this->registry = $registry;
        $this->scopeConfig = $scopeConfig;
        $this->session = $customerSession;
        $this->logger = $logger;
        $this->apiUrl = $this->getEpicorApiUrl() . 'branchName';
        $this->epicorName = $this->getEpicorApiUser();
        $this->epicorPwd = $this->getEpicorApiPassword();
    }

    /**
     * @return Epicor Module Status
     */
    public function getModuleStatus()
    {
        return $this->scopeConfig->getValue('epicor/general/enable', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return Epicor API URL
     */
    public function getEpicorApiUrl()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(self::EPICOR_API_URL, $storeScope);
    }

    /**
     * @return Epicor API User
     */
    public function getEpicorApiUser()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(self::EPICOR_API_USER, $storeScope);
    }

    /**
     * @return Epicor API Password
     */
    public function getEpicorApiPassword()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(self::EPICOR_API_PASSWORD, $storeScope);
    }

    /**
     * @return Epicor Guest Entity
     */
    public function getGuestEntity()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(self::EPICOR_GUEST_ENTITY, $storeScope);
    }

    /**
     * @return Epicor Guest Branch
     */
    public function getGuestBranch()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(self::EPICOR_GUEST_HOME, $storeScope);
    }

    /**
     * @return Product Price Using ID
     */
    public function getProductPriceById($pid)
    {
        if ((!empty($this->getModuleStatus())) && ($this->getModuleStatus() != 0)) {
            try {
                $data = $this->pullDataFromEpicor($pid);
                if (isset($data[0]['customerPrice'])) {
                    return $data[0]['customerPrice'];
                } else {
                    $this->logger->debug("Something went wrong in product price function");
                }
            } catch (\Exception $e) {
                $this->logger->debug("SKU:" . $pid . " Exception>> Gateway request error to fetch Price" . $e->getMessage());
                $this->logger->debug("Something went wrong in product price function");
            }
        }
    }

    /**
     * @return Fetching Product Data From Epicor
     */
    function pullDataFromEpicor($ids)
    {
        /*Check user session to get it's Epicor ID*/
        if ($this->session->isLoggedIn()) {
            $entityId = $this->session->getEpicorId();
        } else {
            /*If customer is guest user*/
            $entityId = $this->getGuestEntity();
            $homebranch = $this->getGuestBranch();
            $this->session->setEpicorId($entityId);
            $this->session->setHomebranch($homebranch);
            //$this->getStore($homebranch);
        }
        try {
            $request = array("user" => $this->epicorName, "password" => $this->epicorPwd, "entityid" => $entityId, "storeid" => "home", "skus" => $ids);
            $result = $this->getProductFromEpicore($request);
            $json = json_decode($result);
            return ($json);
        } catch (\Exception $e) {
            $this->logger->debug("Exception >> Gateway request error to fetch Price" . $e->getMessage());
        }
    }

    /**
     * @return Epicor Request Prepration
     */
    public function getProductFromEpicore($request)
    {
        $skus = $request['skus'];
        $username = $request['user'];
        $password = $request['password'];
        $custId = $request['entityid'];

        $partIdList = "<PartIdentifiersList>";
        foreach ($skus as $sku) {
            $partIdList = $partIdList . "<PartIdentifiers><EclipsePartNumber>" . $sku . "</EclipsePartNumber></PartIdentifiers>";
        }
        $partIdList = $partIdList . "</PartIdentifiersList>";

        $xmlRequest = "<IDMS-XML xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xmlns:xsd='http://www.w3.org/2001/XMLSchema'>
                        <MassProductInquiry><Security><SitePass>
                        <LoginID>" . $username . "</LoginID>
                        <Password>" . $password . "</Password>
                        <ActiveCustomer>" . $custId . "</ActiveCustomer>
                        </SitePass></Security>" . $partIdList . "<EntityID>" . $custId . "</EntityID>" . "<CalculatePriceData>Yes</CalculatePriceData>
                        <IncludeRichContent>Yes</IncludeRichContent>
                        <CalculateAvailabilityData>Yes</CalculateAvailabilityData>
                        </MassProductInquiry></IDMS-XML>";

        $redirect_url = $this->getEpicorApiUrl();
        $ch = curl_init($redirect_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml', 'Connection: Keep-Alive'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        $response = curl_exec($ch);

        $responseData = $this->xml2array($response, $get_attributes = 3, $priority = 'tag');
        $responseData = array_shift($responseData);
        $json_data = array("Catalog" => json_decode(json_encode($responseData), true));
        return json_encode($json_data);
    }

    /**
     * @return Convert Attributes Values To XML
     */
    public function xml2array($contents, $get_attributes = 1, $priority = 'tag')
    {
        if (!$contents) return array();

        if (!function_exists('xml_parser_create')) {
            return array();
        }
        $parser = xml_parser_create('');
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);
        if (!$xml_values) return;
        $xml_array = array();
        $parents = array();
        $opened_tags = array();
        $arr = array();
        $current = &$xml_array;
        $repeated_tag_index = array();
        foreach ($xml_values as $data) {
            unset($attributes, $value);
            extract($data);
            $result = array();
            $attributes_data = array();
            if (isset($value)) {
                if ($priority == 'tag') $result = $value;
                else $result['value'] = $value;
            }
            if (isset($attributes) and $get_attributes) {
                foreach ($attributes as $attr => $val) {
                    if ($attr == 'ResStatus') {
                        $current[$attr][] = $val;
                    }
                    if ($priority == 'tag') $attributes_data[$attr] = $val;
                    else $result['attr'][$attr] = $val;
                }
            }
            if ($type == "open") {
                $parent[$level - 1] = &$current;
                if (!is_array($current) or (!in_array($tag, array_keys($current)))) {
                    $current[$tag] = $result;
                    if ($attributes_data) $current[$tag . '_attr'] = $attributes_data;
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    $current = &$current[$tag];
                } else {
                    if (isset($current[$tag][0])) {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                        $repeated_tag_index[$tag . '_' . $level]++;
                    } else {
                        $current[$tag] = array(
                            $current[$tag],
                            $result
                        );
                        $repeated_tag_index[$tag . '_' . $level] = 2;
                        if (isset($current[$tag . '_attr'])) {
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset($current[$tag . '_attr']);
                        }
                    }
                    $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                    $current = &$current[$tag][$last_item_index];
                }
            } elseif ($type == "complete") {
                if (!isset($current[$tag])) {
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $attributes_data) $current[$tag . '_attr'] = $attributes_data;
                } else {
                    if (isset($current[$tag][0]) and is_array($current[$tag])) {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                        if ($priority == 'tag' and $get_attributes and $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag . '_' . $level]++;
                    } else {
                        $current[$tag] = array(
                            $current[$tag],
                            $result
                        );
                        $repeated_tag_index[$tag . '_' . $level] = 1;
                        if ($priority == 'tag' and $get_attributes) {
                            if (isset($current[$tag . '_attr'])) {
                                $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                                unset($current[$tag . '_attr']);
                            }
                            if ($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag . '_' . $level]++;
                    }
                }
            } elseif ($type == 'close') {
                $current = &$parent[$level - 1];
            }
        }
        return ($xml_array);
    }
}
