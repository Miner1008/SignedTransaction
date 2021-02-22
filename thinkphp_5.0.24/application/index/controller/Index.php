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
		$private_key = $crypto_env->decryptTRC20WalletPrivateKey();
		$to_addr = 'TYKQvmp6yaYqKUNuLmftjDHhnEHcYkMGDQ';
		
		$tron->setAddress($from_addr);
		$balance = $tron->getBalance(null, true);
		if ($balance < 0.1) {
			var_dump('Insufficient TRX Balance');
			return;
		}

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
		
        //get decimals
        $function = "decimals";
        $params = [];
        $result = $tron->getTransactionBuilder()->triggerConstantContract($contractAbi, $contractAddress_HEX, $function, $params, $fromAddress_HEX);
        $decimals = $result[0]->toString();
        
        if (!is_numeric($decimals)) {
            throw new Exception("Token decimals not found");
        }
		
        //get usdt balance 
        $function = "balanceOf";
        $params = [ str_pad($fromAddress_HEX, 64, "0", STR_PAD_LEFT) ];

        $result = $tron->getTransactionBuilder()->triggerConstantContract($contractAbi, $contractAddress_HEX, $function, $params, $fromAddress_HEX);
        $usdt_balance = $result[0]->toString();
        if (!is_numeric($usdt_balance)) {
            throw new Exception("Token balance not found");
        }
        
        $usdt_balance = bcdiv($usdt_balance, bcpow("10", $decimals), $decimals);
        if ($usdt_balance < $payAmount) {
        	var_dump('Insufficient USDT Balance');
        	return;
        }

		//transfer usdt
        $payAmount = bcmul($payAmount, bcpow("10", $decimals, 0), 0);
        $params = [$toAddress_HEX, $payAmount];

        $function = "transfer";
        $tx = $tron->getTransactionBuilder()->triggerSmartContract($contractAbi,
        	$contractAddress_HEX, 
        	$function, 
        	$params, 
        	100000000,
        	$fromAddress_HEX,
        	0,
        	0);
		
        $tron->setPrivateKey($private_key);
        $signedTransaction = $tron->signTransaction($tx);
        $response = $tron->sendRawTransaction($signedTransaction);
        echo '<pre>' , "transaction status:  ". $response['result'] , '</pre>';
        echo '<pre>' , "transaction hash:    ". $response['txid'] , '</pre>';
    }
}
