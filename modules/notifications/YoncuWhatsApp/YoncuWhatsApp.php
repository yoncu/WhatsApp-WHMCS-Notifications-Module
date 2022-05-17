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

class YoncuWhatsApp implements NotificationModuleInterface
{
    use DescriptionTrait;
    public function __construct()
    {
  		if(!is_file("modules/notifications/YoncuWhatsApp/logo.png") or filesize("modules/notifications/YoncuWhatsApp/logo.png") < 100){
			chmod("modules/notifications/YoncuWhatsApp/", 0777);
			$Curl = curl_init();
			curl_setopt($Curl, CURLOPT_URL, "https://www.yoncu.com/resimler/genel/logo.png");
			curl_setopt($Curl, CURLOPT_HEADER, false);
			curl_setopt($Curl, CURLOPT_ENCODING, false);
			curl_setopt($Curl, CURLOPT_COOKIESESSION, false);
			curl_setopt($Curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($Curl, CURLOPT_HTTPHEADER, array(
				'Connection: keep-alive',
				'Accept: application/json',
				'User-Agent: '.$_SERVER['SERVER_NAME'],
				'Referer: http://www.yoncu.com/',
				'Cookie: YoncuKoruma='.$_SERVER['SERVER_ADDR'].';YoncuKorumaRisk=0',
			));
			$logo=curl_exec($Curl);
			curl_close($Curl);
			file_put_contents("modules/notifications/YoncuWhatsApp/logo.png",$logo);
  		}
        $this->setDisplayName('YoncuWhatsApp');
		$this->setLogoFileName('logo.png');
    }
    public function settings()
    {
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
        ];
    }
    public function testConnection($settings)
    {
        if (empty($settings['yoncu_api_id']) || empty($settings['yoncu_api_key'])){
            throw new \Exception('API Bağlantı Hatası.');
        }
    }
    public function notificationSettings()
    {
        return [
            'WhatsAppMessage' => [
                'FriendlyName' => 'Gönderilecek Mesaj',
	            'Type' => 'textarea',
	            'Rows' => '3',
	            'Cols' => '120',
            	'Default' => "Sayın {fullname},\nİşleminiz gerçekleştirilmiştir. Bizi tercih ettiğiniz için teşekkür ederiz.",
                'Placeholder' => 'WhatsApp ile Gönderilecek Mesaj',
            ],
		];
    }
    public function getDynamicField($fieldName, $settings)
    {
        return [];
    }
	function YoncuWhatsApp_logModuleCall($Is,$Req,$Res){
	    logModuleCall('YoncuWhatsApp',$Is,var_export($Req,true),null,var_export($Res,true),array());
	}
    public function sendNotification(NotificationInterface $notification, $moduleSettings, $notificationSettings)
    {
  		foreach((array)$notification as $tmp=>$attributes){
	  		foreach((array)$attributes as $tmp=>$attribute){
	  			$attribute=(array)$attribute;
		  		foreach((array)$attribute as $tmp=>$ff){
		  			if(is_string($ff) and strstr($ff,'?userid=')){
		  				list($tmp,$UserID)=explode('?userid=',$ff,2);
		  				$results = localAPI('GetClientsDetails',['clientid'=>$UserID,'stats'=>true]);
		  				if(isset($results['telephoneNumber']) and strstr($results['telephoneNumber'],'+')){
		  					$results['telephoneNumber']=str_replace('.','',$results['telephoneNumber']);
		  					$Mesaj=$notificationSettings["WhatsAppMessage"];
		  					$Mesaj=str_replace('{fullname}',$results['fullname'],$Mesaj);
		  					$Mesaj=str_replace('{email}',$results['email'],$Mesaj);
		  					$Mesaj=str_replace('{userid}',$results['userid'],$Mesaj);
		  					$Mesaj=str_replace('{phone}',$results['telephoneNumber'],$Mesaj);
							$Curl = curl_init();
							curl_setopt($Curl, CURLOPT_URL, "https://www.yoncu.com/API/WhatsApp/".$moduleSettings['yoncu_service_id']."/Send");
							curl_setopt($Curl, CURLOPT_HEADER, false);
							curl_setopt($Curl, CURLOPT_ENCODING, false);
							curl_setopt($Curl, CURLOPT_COOKIESESSION, false);
							curl_setopt($Curl, CURLOPT_RETURNTRANSFER, true);
							curl_setopt($Curl, CURLOPT_USERPWD,$moduleSettings['yoncu_api_id'].":".$moduleSettings['yoncu_api_key']);
							curl_setopt($Curl, CURLOPT_HTTPHEADER, array(
								'Connection: keep-alive',
								'Accept: application/json',
								'User-Agent: '.$_SERVER['SERVER_NAME'],
								'Referer: http://www.yoncu.com/',
								'Cookie: YoncuKoruma='.$_SERVER['SERVER_ADDR'].';YoncuKorumaRisk=0',
							));
							$Post=json_encode(["Phone"=>$results['telephoneNumber'],"Message"=>$Mesaj]);
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
