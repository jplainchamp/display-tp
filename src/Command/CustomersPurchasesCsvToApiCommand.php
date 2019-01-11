<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomersPurchasesCsvToApiCommand extends Command
{
    protected static $defaultName = 'app:customers-purchases-csv-to-api';

    protected function configure()
    {
        $this
            ->setDescription('Import files customers.csv and purchases.csv to send to API.')
            ->setHelp('Enter this command with argument path like "/home/filescsv", "path" is the directory\'s location where the csv files are located.')
            ->addArgument('path', InputArgument::REQUIRED, 'Path indicating directory location which contains files csv.');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $customersCsv = $path."/customers.csv";
        $purchasesCsv = $path."/purchases.csv";

        $errors = array();

        if (!file_exists($path)) $errors[] = 'Directory not found, path error check them !';
        if (!file_exists($customersCsv)) $errors[] = 'File customers.csv not found in '.$path;
        if (!file_exists($purchasesCsv)) $errors[] = 'File purchases.csv not found in '.$path;

        if(count($errors) > 0)
        {
            foreach ($errors as $e)
            {
                $output->writeln($e);
            }
            exit;
        }

        $data_json = $this->csvToJson($customersCsv, $purchasesCsv);
        $url = 'https://api.display-interactive.com/v1/customers';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $output->writeln($result);
        $output->writeln('End !');

    }

    private function csvToJson($customersCsv, $purchasesCsv)
    {
        $customers = $this->csvToArray($customersCsv);
        $purchases = $this->csvToArray($purchasesCsv);

        $arrayFinal = array();
        $arrayPurchases = array();
        foreach ($purchases as $purchase)
        {
            $arrayPurchases[] = array(
                'customer_id' => $purchase[1],
                'product_id' => $purchase[2],
                'price' => (float) $purchase[4],
                'currency' => $purchase[5],
                'quantity' => (int) $purchase[3],
                'purchased_at' => $purchase[6],
            );
        }

        foreach ($customers as $customer)
        {
            $customerPurchases = array();
            foreach ($arrayPurchases as $arrP)
            {
                if($arrP['customer_id'] == $customer[0])
                {
                    unset($arrP['customer_id']);
                    $customerPurchases[] = $arrP;
                }
            }

            $arrayFinal[] = array(
                'salutation' => $customer[1] == '1' ? 'mme' : 'm',
                'last_name' => $customer[2],
                'first_name' => $customer[3],
                'email' => $customer[6],
                'purchases' => $customerPurchases
            );
        }

        $json = json_encode($arrayFinal);

        return $json;
    }

    private function csvToArray($file)
    {
        $array = array();

        if (($csv = fopen($file, "r")) !== FALSE)
        {
            $firstLine = fgetcsv($csv, 0, ";", '"');

            $correctFirstLine = array();
            foreach ($firstLine as $fl)
            {
                // On vérifie si la premiere ligne du fichier contient une virgule et on la remplace par ;
                if (strpos($fl, ',')) {
                    $fl = str_replace(',', ';', $fl);
                    $arr = explode(';', $fl);
                    $correctFirstLine[] = $arr[0];
                    $correctFirstLine[] = $arr[1];
                }
                else
                {
                    $correctFirstLine[] = $fl;
                }
            }

            // Compte le nombre de colonne pour la première ligne
            $numCols = count($correctFirstLine);

            while ($row = fgetcsv($csv, 0, ";", '"'))
            {
                if($numCols == count($row)) {
                    if(strpos($row[0], '/')) {
                        $arr = explode('/', $row[0]);
                        $row[0] = $arr[1];
                    }
                    $array[] = $row;
                }
            }
            fclose($csv);
        }

        return $array;
    }
}