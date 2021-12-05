<?php

namespace Ecommsolvers\ImportCustomers\Console\Command;

use Ecommsolvers\ImportCustomers\Logger\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\State;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Area;


/**
 * Class CreateUsersFromCsvCommand
 */
class CreateUsersFromCsvCommand extends Command
{
    const FILE = 'file';

    private StoreManagerInterface $storeManager;
    private IndexerFactory $indexerFactory;
    private CustomerRepositoryInterface $customerRepository;
    private State $state;
    private ObjectManagerInterface $objectManager;
    private Logger $logger;

    public function __construct(StoreManagerInterface $storeManager, IndexerFactory $indexerFactory, Logger $logger, CustomerRepositoryInterface $customerRepository, ObjectManagerInterface $objectManager, State $state, string $name = null)
    {
        $this->state = $state;
        $this->storeManager = $storeManager;
        $this->indexerFactory = $indexerFactory;
        $this->logger = $logger;
        $this->objectManager = $objectManager;
        $this->customerRepository = $customerRepository;

        $this->setName('ecomm:import:customer')->setDescription('Ecomm Import Customer');

        parent::__construct($name);

        $this->addOption(
            self::FILE,
            null,
            InputOption::VALUE_REQUIRED,
            'File'
        );

    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $date = new \DateTime();
        $failed_count = $success_count = $counter = 0;
        $file = $input->getOption(self::FILE);

        $this->state->setAreaCode(Area::AREA_FRONTEND);

        $this->logger->info(sprintf("%s command started at: %s ", $this->getDescription(), $date->format('Y-m-d H:i:s')));

        $file = fopen($file, 'r');

        while (($line = fgetcsv($file)) !== false) {

            $counter++;

            if ($counter == 1) {
                continue;
            }

            try {

                $websiteId = $this->storeManager->getWebsite($line[3])->getWebsiteId();
                $store = $this->storeManager->getStore($line[4]);

                $customer = $this->objectManager->get('\Magento\Customer\Api\Data\CustomerInterfaceFactory')->create();
                $customer->setWebsiteId($websiteId);
                $customer->setStoreId($store->getId());
                $customer->setEmail($line[0]);
                $customer->setFirstname($line[1]);
                $customer->setLastname($line[2]);
                $customer->setCreatedAt($line[6]);

                $hashedPassword = $this->objectManager->get('\Magento\Framework\Encryption\EncryptorInterface')->getHash($line[8], true);

                $this->customerRepository->save($customer, $hashedPassword);
                $success_count++;
            } catch (\Exception $exception) {
                $this->logger->error(sprintf('Import failed for address: %s exception: %s', $line[0], $exception->getMessage()));
                $failed_count++;
            }

        }

        fclose($file);

        $indexer = $this->indexerFactory->create();
        $indexer->load('customer_grid');

        try {
            $indexer->reindexAll();
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Reindexing failed:  exception: %s', $e->getMessage()));
        }

        $this->logger->info(sprintf("%s command finished. Lines in input file: %d , Success: %d, Failed %d ", $this->getDescription(), $counter - 1, $success_count, $failed_count));

    }
}
