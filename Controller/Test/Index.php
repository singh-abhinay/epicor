<?php

namespace Abhinay\Epicor\Controller\Test;

/**
 * Class Index
 * @package Abhinay\Epicor\Controller\Test
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Abhinay\Epicor\Helper\Data
     */
    protected $helper;

    /**
     * Index constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Abhinay\Epicor\Helper\Data $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Abhinay\Epicor\Helper\Data $helper
    )
    {
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     *
     */
    public function execute()
    {
        $productId = 10;
        $epicorCall = $this->helper->getProductPriceById($productId);
        print_r($epicorCall); die("===========Epicor Call===");
    }
}
