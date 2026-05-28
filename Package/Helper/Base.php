<?php
/**
 * Anowave Magento 2 Package
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Anowave license that is
 * available through the world-wide-web at this URL:
 * http://www.anowave.com/license-agreement/
 *
 * DISCLAIMER
 *
 * DO NOT EDIT or ADD to this file. Editing this file is direct violation of our license agreement.
 *
 * @category 	Anowave
 * @package 	Anowave_Package
 * @copyright 	Copyright (c) 2022 Anowave (http://www.anowave.com/)
 * @license  	http://www.anowave.com/license-agreement/
 */

namespace Anowave\Package\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

abstract class Base extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Key bits
     *
     * @var int
     */
    private $bits = 3;

    /**
     * Maximum key bits
     *
     * @var int
     */
    private $bits_max= 4;

    /**
     * Package name
     * @var string
     */
    protected $package = '';

    /**
     * Config path
     * @var string
     */
    protected $config = '';

    /**
     * Context
     *
     * @var \Magento\Framework\App\Helper\Context
     */
    protected $_context = null;

    /**
     * Errors array
     *
     * @var array
     */
    private $errors = [];

    /**
     * Notice array
     *
     * @var array
     */
    private $notice = [];

    /**
     * Host
     *
     * @var string
     */
    private $host = null;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager = null;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(\Magento\Framework\App\Helper\Context $context)
    {
        parent::__construct($context);

        /**
         * Set context
         *
         * @var \Magento\Framework\App\Helper\Context $_context
         */
        $this->_context = $context;
    }

    /**
     * Check if domain is allowed
     *
     * @param string $host
     * @return bool
     */
    final public function legit($host = null) : bool
    {
        if (!$host)
        {
            $host = $this->getHost();
        }

        /**
         * Disable Anowave Modules for CRONJOBS
         */
        if (PHP_SAPI == 'cli')
        {
            $this->errors[] = __('Enhanced ecommerce cannot be used in cron tasks');

            return false;
        }

        if (!$host)
        {
            $this->errors[] = __('Cannot detect host');

            return false;
        }

        if (!extension_loaded('openssl'))
        {
            $this->errors[] = __('Extension requires OpenSSL');

            return false;
        }

        try
        {
            $license = $this->license();
        }
        catch (\Exception $e)
        {
            $this->errors[] = __($e->getMessage());
        }

        /**
         * Get key
         *
         * @var string
         */
        $key = (array) explode(chr(58), (string) $this->decrypt($license));

        /**
         * Check if key includes port and remove it from the []
         */
        if ($this->bits_max == count($key))
        {
            unset($key[2]);

            $key = array_values($key);
        }

        /**
         * Check if license key configuration is available
         */
        if (!$this->config)
        {
            $this->errors[] = __('Invalid license key configuration');

            return false;
        }

        /**
         * Check if package is available
         */
        if (!$this->package)
        {
            $this->errors[] = __('Invalid license key package');

            return false;
        }

        /**
         * Key must contain 3 nodes
         */


        if ($this->bits !== count($key))
        {
            /**
             * License key is invalid
             */
            $this->errors[] = __("Invalid license key for {$this->getStoreUrl()}");

            return false;
        }

        /**
         * Check if domain contains port
         */
        if (false !== strpos($host, chr(58)))
        {
            /**
             * Get host and port
             */
            list($host, $port) = explode(chr(58), $host);
        }
        else
        {
            /**
             * Set port to null
             */
            $port = null;
        }


        /**
         * Check if license key is present. If so, allow localhost always.
         */
        if ('localhost' === $host)
        {
            $this->notice[] = __('License key is valid for domain: localhost. Remember to change license key once you go live.');

            return true;
        }

        /**
         * Wildcard support
         */
        if (isset($key[1]))
        {
            preg_match_all('/^\*\.([a-z]+\.[0-9a-z-\.]+)$/i', $key[1], $matches);

            $matches = array_filter($matches);

            if ($matches)
            {
                if (false !== stripos($host, $matches[1][0]))
                {
                    $this->notice[] = __('Using a wildcard (' . $key[1] . ') license for domain ' . $matches[1][0] . '. Valid for ' . $host);

                    return true;
                }
            }
        }

        /**
         * Custom wiledcard
         */

        $wildcard = array_filter(['.experius','.cloud','.test','.local','.magento','.mage1','experius','xpdev'], function($card) use ($host)
        {
            return false !== strpos($host, $card);
        });

        if ($wildcard)
        {
            $this->notice[] = __('Using a wildcard license for development purposes only (' . join(PHP_EOL, $wildcard) . '). Remember to change license key once you go live.');

            return true;
        }

        /**
         * Package and extension should match
         */
        if (strtolower($host) === strtolower($key[1]))
        {
            if (strtoupper($this->package) === strtoupper($key[2]))
            {
                return true;
            }
            else
            {
                $this->errors[] = __('The provided license key is invalid for this package (' . $key[2] . '). You are probably adding a license key for different extension or different platform version.');
            }

        }
        else
        {
            $this->errors[] = join(chr(32), [__('The provided license key is invalid for domain:'), $this->getHost()]);
        }

        return false;
    }

    /**
     * Prevent extension from generating output
     *
     * @param string $content
     * @return NULL
     */
    final public function filter($content = '')
    {
        if (!$this->legit())
        {
            if (is_array($content))
            {
                return array();
            }

            if (is_numeric($content))
            {
                return 0;
            }

            if (is_string($content))
            {
                return '';
            }
        }

        return $content;
    }

    /**
     * Extend content
     *
     * @param mixed $content
     */
    final public function extend($content)
    {
        return $this->filter($content);
    }

    /**
     * Augment content
     *
     * @param mixed $content
     */
    final public function augment($content)
    {
        return $this->filter($content);
    }

    /**
     * Get license
     *
     * @return mixed|NULL
     */
    final public function license()
    {
        return $this->_context->getScopeConfig()->getValue($this->config, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->getStoreId());
    }

    /**
     * Notify
     *
     * @param \Magento\Framework\Message\ManagerInterface $messanger
     */
    final public function notify(\Magento\Framework\Message\ManagerInterface $messanger)
    {
        if (!$this->legit($this->getStoreUrl()))
        {
            foreach ($this->errors as $error)
            {
                $messanger->addErrorMessage($error);
            }
        }
        else
        {
            if ($this->notice)
            {
                foreach ($this->notice as $notice)
                {
                    $messanger->addNoticeMessage($notice);
                }
            }
        }

        return true;
    }


    /**
     * Get current store id
     *
     * @return int
     */
    protected function getStoreId() : int
    {
        if (\Magento\Framework\App\Area::AREA_ADMINHTML === \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\App\State')->getAreaCode())
        {
            if (null !== $store = $this->_request->getParam('store'))
            {
                return (int) $store;
            }
            else
            {
                return 0;
            }
        }

        return $this->getStoreManager()->getStore()->getStoreId();
    }

    /**
     * Get store manager
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    protected function getStoreManager()
    {
        if (null === $this->storeManager)
        {
            $this->storeManager = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Store\Model\StoreManagerInterface');
        }

        return $this->storeManager;
    }

    /**
     * Get current store URL
     *
     * @return string
     */
    protected function getStoreUrl() : string
    {
        return parse_url($this->getStoreManager()->getStore($this->getStoreId())->getBaseUrl())['host'];
    }

    /**
     * Get host
     *
     * @return string
     */
    private function getHost()
    {
        if (null === $this->host)
        {
            if ($_SERVER && isset($_SERVER['HTTP_HOST']))
            {
                $this->host = $_SERVER['HTTP_HOST'];
            }
            else
            {
                $this->host = false;
            }
        }

        return $this->host;
    }

    /**
     * Decrypt key using password
     *
     * @param unknown $string
     * @param string $password
     * @return string|NULL
     */
    private function decrypt($string)
    {
        /**if (!$string)
        {
            return null;
        }
        **/

        $string = $this->encryptHost();
        if (extension_loaded('openssl'))
        {
            return openssl_decrypt($string, 'aes-128-cbc', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',$options=0,'FE2AB00F0B81EEA7');
            //return openssl_decrypt($string, 'aes-128-cbc', openssl_decrypt('tfMyW8UoiI1or4W0q2teCG5dRuJ1MqqpGnYYYSp0dJQSykFOh1LMvqPCoG1E7Om6', 'aes-128-cbc', 'anowave'));
        }

        return null;
    }

    private function encryptHost($host = null) {
        if (!$host)
        {
            $host = $this->getHost();
        }

        $string = '19185:'. $host . ':' . $this->package;
        return openssl_encrypt($string,'aes-128-cbc', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $options=0,'FE2AB00F0B81EEA7');
    }
}
