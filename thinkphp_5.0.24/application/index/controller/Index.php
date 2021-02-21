<?php
namespace app\index\controller;
use app\index\model\CryptoEnv;
use IEXBase\TronAPI\Tron;

class Index
{
    public function index()
    {
        return 'Payout TRC20 USDT Sample';
    }

    public function payout()
    {
        return view();
    }
    
    public function payoutUSDT()
    {
    	$crypto_env = new CryptoEnv(false);

    	$fullNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
        $solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
        $eventServer = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');

        try {
            $tron = new \IEXBase\TronAPI\Tron($fullNode, $solidityNode, $eventServer);
        } catch (\IEXBase\TronAPI\Exception\TronException $e) {
            exit($e->getMessage());
        }

        $payAmount = 0.5;
		$from_addr = $crypto_env->GetTRC20WalletPublicKey();
		$private_key = $crypto_env->GetTRC20WalletPrivateKey();
		$to_addr = 'TYKQvmp6yaYqKUNuLmftjDHhnEHcYkMGDQ';
		
		$tron->setAddress($from_addr);
		$balance = $tron->getBalance(null, true);
		echo '<pre>' , var_dump(array("trx balance" => $balance)) , '</pre>';

        $contractAbi = json_decode($crypto_env->GetTRC20USDTABI(), true);

        $contractAddress = $crypto_env->GetTRC20USDTADDRESS();

        $contractAddress_HEX = $tron->toHex($contractAddress);
        $fromAddress_HEX = $tron->toHex($from_addr);
        $toAddress_HEX = $tron->toHex($to_addr);
        
        //get symbol
        $function = "symbol";
        $params = [];
        
        $result = $tron->getTransactionBuilder()->triggerConstantContract($contractAbi, $contractAddress_HEX, $function, $params, $fromAddress_HEX);
        $symbol = $result[0];
		
		echo '<pre>' , var_dump(array("usdt symbol" => $symbol)) , '</pre>';
		
        //get decimals
        $function = "decimals";
        $params = [];
        $result = $tron->getTransactionBuilder()->triggerConstantContract($contractAbi, $contractAddress_HEX, $function, $params, $fromAddress_HEX);
        $decimals = $result[0]->toString();
        
        if (!is_numeric($decimals)) {
            throw new Exception("Token decimals not found");
        }
        echo '<pre>' , var_dump(array("usdt decimals" => $decimals)) , '</pre>';
		
        //get usdt balance 
        $function = "balanceOf";
        $params = [ str_pad($fromAddress_HEX, 64, "0", STR_PAD_LEFT) ];

        $result = $tron->getTransactionBuilder()->triggerConstantContract($contractAbi, $contractAddress_HEX, $function, $params, $fromAddress_HEX);
        $usdt_balance = $result[0]->toString();
        if (!is_numeric($usdt_balance)) {
            throw new Exception("Token balance not found");
        }
        
        $usdt_balance = bcdiv($usdt_balance, bcpow("10", $decimals), $decimals);

		echo '<pre>' , var_dump(array("usdt balance" => $usdt_balance)) , '</pre>';

		//transfer usdt
        $payAmount = bcmul($payAmount, bcpow("10", $decimals, 0), 0);
        $params = [$toAddress_HEX, $payAmount];

        $function = "transfer";
		echo '<pre>' , var_dump(array("payout usdt amount" => $payAmount)) , '</pre>';
		
        $tx = $tron->getTransactionBuilder()->triggerSmartContract($contractAbi,
        	$contractAddress_HEX, 
        	$function, 
        	$params, 
        	100000000,
        	$fromAddress_HEX,
        	0,
        	0);
		
		echo '<pre>' , var_dump(array("transaction hash" => $tx)) , '</pre>';

        $tron->setPrivateKey($private_key);
        $signedTransaction = $tron->signTransaction($tx);
		echo '<pre>' , var_dump(array("signedTransaction" => $signedTransaction)) , '</pre>';
		
        $response = $tron->sendRawTransaction($signedTransaction);
        echo '<pre>' , var_dump(array("response" => $response)) , '</pre>';

	    //$parsedRaw =  new \Protocol\Transaction\Raw();
        
        /*$parsedRaw->mergeFromString(hex2str($mutatedTx['raw_data_hex']));
            
        $newTx->setRawData($parsedRaw);
        $signature = Support\Secp::sign($mutatedTx['txID'], $_POST['privkey']);
        $newTx->setSignature([hex2str( $signature )]);

        //check trx & usdt balance;

       /* $crypto_env = new CryptoEnv(true);

        $infura_key = $crypto_env->GetInfuraKey();
        $chain_id = $crypto_env->GetChainID();

        $contractAbi = $crypto_env->GetERC20USDTABI();
        $contractAddress = $crypto_env->GetERC20USDTADDRESS();

        $web3 = new Web3(new HttpProvider(new HttpRequestManager($infura_key, 10)));
        $contract = new Contract(new HttpProvider(new HttpRequestManager($infura_key, 10)), $contractAbi);
        $contractInstance = $contract->at($contractAddress);

        $gasPrice = $crypto_env->getCurrentGasPrices();
        
        $private_key = $crypto_env->GetERC20WalletPrivateKey();        
        $from_addr = '0x9cB1741ecb8971C00F4d53469eF0cB713B579237';
        $to_addr = '0x079c0e34ad788419B6086e588ad2a46f86126268';
        $sendValue = 1500000;
        
        
        $txHash = $contractInstance->getData(
            'transfer',
            $to_addr,
            (string)$sendValue    
        );

        $web3->eth->estimateGas(
            ['from'=>$to_addr, 'to'=>$to_addr, 'value'=>'0x0', 'data'=>('0x'.$txHash)], function ($err, $gas) use (&$gasLimit) {
            if ($err !== null) {
                var_dump($err->getMessage());
                exit();
            } else {
                $gasLimit = 49042 + floatval((string)$gas);
            }
        });
        
        $gasFee = ($gasLimit * $gasPrice['medium']) / 1000000000;

        
        //get eth balance of wallet
        $web3->eth->getBalance($from_addr, function ($err, $balance) use (&$eth_balance){
            if ($err !== null) {
                var_dump($err->getMessage());
                exit();
            }
            $eth_balance = floatval((string)$balance) / (10**18);
        });
        
        //get usdt balance of wallet
        $contractInstance->call('balanceOf', $from_addr, function ($err, $balance) use (&$usdt_balance){
            if ($err !== null) {
                var_dump($err->getMessage());
                exit();
            }

            $usdt_balance = floatval((string)($balance[0])) / (10**6);
        });

        
        $web3->eth->getTransactionCount($from_addr, function ($err, $result) use (&$nonce) {
            if ($err !== null) {
                var_dump($err->getMessage());
                exit();
            }

            $nonce = $result;
        });

        $tx = new Tx([
            "nonce"=> '0x' . (preg_match('/^([0-9]{1})$/', $nonce) ? '0' . $this->nonce : $web3->utils->toHex($nonce)),
            "gasPrice"=> '0x' . $web3->utils->toHex((string)$web3->utils->toWei((string)$gasPrice['medium'], 'gwei')),
            "gasLimit"=> '0x' . $web3->utils->toHex($gasLimit),
            "to"=> $contractAddress,
            "value"=> '0x00',
            "data"=> '0x' . $txHash,
            "chainId"=> $chain_id
        ]);
            
        $serializeSignedTx = $tx->sign($private_key);
            
        $web3->eth->sendRawTransaction('0x' . $serializeSignedTx, function ($err, $result) use (&$transaction) {
            if ($err !== null) {
                var_dump($err->getMessage());
                exit();
            }
            
            $transaction = $result;
            var_dump($transaction);
        });

        //return $res;*/
        var_dump('transaction hash will be appeared!');
    }
	
}
