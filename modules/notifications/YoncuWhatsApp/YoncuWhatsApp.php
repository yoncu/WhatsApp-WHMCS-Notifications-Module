<?php
namespace WHMCS\Module\Notification\YoncuWhatsApp;
use WHMCS\Config\Setting;
use WHMCS\Exception;
use WHMCS\Mail\Template;
use WHMCS\Module\Notification\DescriptionTrait;
use WHMCS\Module\Contracts\NotificationModuleInterface;
use WHMCS\Notification\Contracts\NotificationInterface;
use WHMCS\Notification\Rule;
use WHMCS\Utility\Environment\WebHelper;

class YoncuWhatsApp implements NotificationModuleInterface{
    use DescriptionTrait;
    public function __construct(){
  		if(!is_file(__DIR__."/logo.png") or filesize(__DIR__."/logo.png") < 100){
			chmod(__DIR__, 0777);
			$Curl = curl_init();
			curl_setopt($Curl, CURLOPT_HEADER, false);
			curl_setopt($Curl, CURLOPT_ENCODING, false);
			curl_setopt($Curl, CURLOPT_COOKIESESSION, false);
			curl_setopt($Curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($Curl, CURLOPT_USERAGENT,$_SERVER['SERVER_NAME'].__FILE__.'#'.date("d-m-Y H:i:s",filemtime(__FILE__)));
			curl_setopt($Curl, CURLOPT_URL, "https://www.yoncu.com/YoncuTest/YoncuSec_Token");
			$YoncuSecToken	= curl_exec($Curl);
			curl_setopt($Curl, CURLOPT_URL, "https://www.yoncu.com/resimler/genel/logo.png");
			curl_setopt($Curl, CURLOPT_HTTPHEADER,['Cookie: YoncuKorumaRisk=0;YoncuSec='.$YoncuSecToken]);
			$logo=curl_exec($Curl);
			curl_close($Curl);
			file_put_contents(__DIR__."/logo.png",$logo);
  		}
        $this->setDisplayName('YoncuWhatsApp');
		$this->setLogoFileName('logo.png');
    }
    public function settings(){
        return [
            'yoncu_service_id' => [
                'FriendlyName' => 'WhatsApp Servis ID',
                'Type' => 'text',
                'Description' => 'Yöncü WhatsApp Hizmet ID',
                'Placeholder' => '12345',
            ],
            'yoncu_api_id' => [
                'FriendlyName' => 'API ID',
                'Type' => 'text',
                'Description' => 'Yöncü Üyeliğinizden Alacağınız API ID',
                'Placeholder' => '12345',
            ],
            'yoncu_api_key' => [
                'FriendlyName' => 'API Key',
                'Type' => 'text',
                'Description' => 'Yöncü Üyeliğinizden Alacağınız API Key',
                'Placeholder' => '827ccb0eea8a706c4c34a16891f84e7b',
            ],
            'yoncu_add_gsms' => [
                'FriendlyName' => 'Tüm Kurallar için Ek GSM Ekle',
                'Type' => 'text',
                'Description' => 'Mesaj kopyasının gönderileceği GSM Numaraları',
                'Placeholder' => '+905554443322,+905332221100',
            ],
        ];
    }
    public function testConnection($settings){
        if (empty($settings['yoncu_api_id']) || empty($settings['yoncu_api_key'])){
            throw new \Exception('API Bağlantı Hatası.');
        }
    }
    public function notificationSettings(){
        return [
            'WhatsAppMessage' => [
                'FriendlyName' => 'Gönderilecek Mesaj',
	            'Type' => 'textarea',
	            'Rows' => '5',
	            'Cols' => '120',
            	'Default' => "Sayın {fullname},\nİşleminiz gerçekleştirilmiştir. Bizi tercih ettiğiniz için teşekkür ederiz.",
                'Placeholder' => 'WhatsApp ile Gönderilecek Mesaj',
            ],
			'WhatsAppAdd'	=> [
                'FriendlyName' => 'Kural için Ek GSM Ekle',
	            'Type' => 'textarea',
	            'Rows' => '2',
	            'Cols' => '240',
                'Placeholder' => '+905554443322,+905332221100',
			],
			'WhatsAppDel'	=> [
                'FriendlyName' => 'Modül Ayarlarındaki Ek GSM Listesini Yok Say',
	            'Type' => 'yesno',
			],
		];
    }
    public function getDynamicField($fieldName, $settings){
        return [];
    }
	function YoncuWhatsApp_logModuleCall($Is,$Req,$Res){
	    logModuleCall('YoncuWhatsApp',$Is,var_export($Req,true),null,var_export($Res,true),array());
	}
    public function sendNotification(NotificationInterface $notification, $moduleSettings, $notificationSettings)
    {
		$BildiriTur	= null;
  		foreach((array)$notification as $tmp=>$attributes){
  			if(is_string($attributes) and stristr('#'.$attributes,'#http')){
	  			if(stristr($attributes,'supporttickets')){
					$BildiriTur	= 'Destek';
	  			}elseif(stristr($attributes,'invoices')){
					$BildiriTur	= 'Fatura';
					if(strstr($attributes,'id=')){
						list($tmp,$invoice)=explode('id=',$attributes,2);
		  				$invoice = localAPI('GetInvoice',['invoiceid'=>$invoice]);
					}
	  			}elseif(stristr($attributes,'clientsservices')){
					$BildiriTur	= 'Hizmet';
	  			}
  			}
	  		foreach((array)$attributes as $tmp=>$attribute){
	  			$attribute=(array)$attribute;
		  		foreach((array)$attribute as $tmp=>$ff){
		  			if(is_string($ff) and strstr($ff,'?userid=')){
		  				list($tmp,$UserID)=explode('?userid=',$ff,2);
		  				$results = localAPI('GetClientsDetails',['clientid'=>$UserID,'stats'=>true]);
		  				$Bildir	= true;
		  				foreach($results as $un=>$uv){
		  					if(is_string($uv) and stristr($uv,'whatsapp')){
		  						if(stristr($uv,'kapalı') or stristr($uv,'kapali')){
		  							$Bildir	= false;
		  							break;
		  						}
		  						if(empty($BildiriTur)){
		  							break;
		  						}
		  						if(stristr($uv,$BildiriTur)){
		  							break;
		  						}else{
		  							$Bildir	= false;
		  							break;
		  						}
		  					}
		  				}
		  				if($Bildir and isset($results['telephoneNumber']) and strstr($results['telephoneNumber'],'+')){
		  					$SendPhones=str_replace('.','',$results['telephoneNumber']).(isset($moduleSettings['yoncu_add_gsms'])&&empty($notificationSettings["WhatsAppDel"])?','.$moduleSettings['yoncu_add_gsms']:null).(empty($notificationSettings["WhatsAppAdd"])?null:','.$notificationSettings["WhatsAppAdd"]);
		  					foreach(explode(',',$SendPhones) as $SendPhone){
		  						$SendPhone=trim($SendPhone);
		  						if(empty($SendPhone)){continue;}
			  					$Mesaj=$notificationSettings["WhatsAppMessage"];
			  					foreach($results as $RN=>$RV){
			  						if(is_string($RN) and is_string($RV)){
					  					$Mesaj=str_replace('{'.$RN.'}',$RV,$Mesaj);
			  						}
			  					}
			  					if(isset($invoice) and is_array($invoice)){
				  					foreach($invoice as $RN=>$RV){
				  						if(is_string($RN) and is_string($RV)){
						  					$Mesaj=str_replace('{'.$RN.'}',$RV,$Mesaj);
				  						}
				  					}
			  					}
			  					if(isset($UserID)){
					  				$Mesaj=str_replace('{userid}',$UserID,$Mesaj);
			  					}
								$Post=json_encode(["Phone"=>$SendPhone,"Message"=>$Mesaj]);
								$Curl = curl_init();
								curl_setopt($Curl, CURLOPT_HEADER, false);
								curl_setopt($Curl, CURLOPT_ENCODING, false);
								curl_setopt($Curl, CURLOPT_COOKIESESSION, false);
								curl_setopt($Curl, CURLOPT_RETURNTRANSFER, true);
								curl_setopt($Curl, CURLOPT_USERAGENT,$_SERVER['SERVER_NAME'].__FILE__.'#'.date("d-m-Y H:i:s",filemtime(__FILE__)));
								if(empty($YoncuSecToken)){
									curl_setopt($Curl, CURLOPT_URL, "https://www.yoncu.com/YoncuTest/YoncuSec_Token");
									$YoncuSecToken	= curl_exec($Curl);
								}
								curl_setopt($Curl, CURLOPT_HTTPHEADER,[
									'Accept: application/json',
									'Cookie: YoncuKorumaRisk=0;YoncuSec='.$YoncuSecToken,
								]);
								curl_setopt($Curl, CURLOPT_USERPWD,$moduleSettings['yoncu_api_id'].":".$moduleSettings['yoncu_api_key']);
								curl_setopt($Curl, CURLOPT_URL, "https://www.yoncu.com/API/WhatsApp/".$moduleSettings['yoncu_service_id']."/Send?Phone=".urlencode($SendPhone));
								curl_setopt($Curl, CURLOPT_POSTFIELDS,$Post);
								$Res=curl_exec($Curl);
	    						$this->YoncuWhatsApp_logModuleCall('curl',$Post,$Res);
		  					}
		  				}
		  			}
		  		}
	  		}
  		}
    }
}
