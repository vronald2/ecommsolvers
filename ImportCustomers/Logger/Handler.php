<?php
namespace Ecommsolvers\ImportCustomers\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/eimport.log';
}
