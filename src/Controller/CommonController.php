<?php 
/*
* Date Création : 07/03/2016
* Auteur : N'VEKOUNOU Moise José
* Nom file : CommonController.php
* Description : tools controller
* Historique Modification
* 27-10-2017 : Method closeContract >> if($option!=null) $oContract->setClosedby("The system"); 
* 03-11-2017 : Creation of Method getLocale >> return $this->get("session")->get("_locale");  
*/
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Tyear;
use App\Entity\Tsweek;
use App\Entity\Errors;
use App\Entity\Payroll;
use App\Entity\Message;
use App\Entity\Billing;
use App\Entity\TsBilling;
use App\Entity\Timesheet;
use App\Entity\TsValidation;
use App\Entity\Agenda;
use App\Entity\TsStudent;
use App\Entity\Hteaching;
use App\Entity\Hadmin;
use App\Entity\Hnoshow;
use App\Entity\Hothers;
use App\Entity\Session;
use App\Entity\Hholiday;
use App\Entity\Histocontract;
use App\Entity\PeriodPay;
use App\Entity\Group;
use App\Libs\PHPExcel\Factory;
use App\Entity\Substitution;
use App\Entity\Criteria;
use App\Entity\Logmanager;
use App\Entity\Invitation;
use App\Entity\Closegroup;
use App\Entity\Absences;
use App\Entity\AbsenceGroup;
use App\Entity\Ccdate;
use App\Entity\DateTransformer;
use App\Entity\Tickets;
use App\Entity\Monthlyhour;
use App\Entity\Tsdoc;
use App\Entity\AbsEmp;
use App\Entity\Usedhour;
use App\Entity\Robot;

/** getAbsenceFormView
* Common controller 
*/
class CommonController
{
    public const
            PERIOD_DAY_AM    = "AM",
            PERIOD_DAY_PM    = "PM",
            PERIOD_DAY_BOTH  = "AM & PM",
            PERIOD_DAY_ALL   = "ALL",
            OPTION_DAY_ONE   = 1,
            OPTION_DAY_TWO   = 2,
            OPTION_DAY_THREE = 3
    ;
    
	//for logging the error of sls
	protected function logger($texte){
		$oLog = new Logmanager();
		$oLog->setLog($texte." by ".$this->getConnectedUser());	
		return;
	}
	protected function getUserIdentity($oUser=null){
		if($oUser==null) $oUser = $this->getTokenUser();
		if($oUser) return $oUser->getEmployee()->getName()." ".$oUser->getEmployee()->getFirstname();
		return null;
	}
	protected function createActivity($rubric,$ssrub){
		$em = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Activities');
		$aActivity = $em->findBy(array('sessionid'=>session_id(),'rubric'=>$rubric,'subrubric'=>$ssrub));
		if(isset($aActivity[0])) return;
		$oUser = $this->getTokenUser();
		if(is_object($oUser)){
			$userid = $oUser->getId();
			return $em->recordActitivy($rubric,$ssrub,$userid);
		}
		return null;
	}
	protected function getFromSession($aTab){
		$aResult = array();
		$session = $this->get('session');
		foreach($aTab as $value){
			$aResult[$value] = $session->get($value);			
		}	
		return $aResult;
	}
	protected function setArraySession($aTab){
		$aResult = array();
		$session = $this->get('session');
		foreach($aTab as $key=>$value){
			$session->set($key,$value);			
		}	
		return true;
	}
	protected function updateView($entity){
		$em = $this->getDoctrine()->getManager();
		$view = $entity->getVue();
		$view = $view+1;
		$entity->setVue($view);
		$em->persist($entity);
		$em->flush();
		return;
	}
	protected function imgresize($cheminsource,$image,$chemindest,$newwidth){
		$imgressource = $this->getImgRessource($cheminsource,$image);
		$imgwidth = imagesx($imgressource);
		$imgheight = imagesy($imgressource);
		$newheight = $this->getHeight($imgwidth,$newwidth,$imgheight);
		header("Content-type: image/png"); //la ligne qui change tout!
		$newimage = imagecreatetruecolor($newwidth,$newheight);	
		imagecopyresampled($newimage, $imgressource, 0, 0, 0, 0, $newwidth, $newheight, $imgwidth, $imgheight);
		imagejpeg( $newimage, "300/".$image );
	}
	protected function getListFile($file){
		if (file_exists($file))
		{
			chmod($file,0700);
			$id_dossier = opendir($file);
			while($element = readdir($id_dossier))
			{	
				if($element != "." && $element != "..") $res[]=$element;
			}
			closedir($id_dossier);
			return $res;
		}
	}
	protected function getImgRessource($chemin,$image){
		$ext=0;
		$aImgExt = array('jpg','gif','png');
		$aExplode = explode(".",$image);
		//Je vérifie si l'extension existe
		if(isset($aExplode[1])){
			$ext = strtolower($aExplode[1]);
			//Est-ce l'extention décrit un file image
			if(!in_array($ext,$aImgExt)) return 0;
		}else{
			return $ext;
		}
		$imagechemin=$chemin.$image;
		if($ext=='jpg') $img_src_resource = imagecreatefromjpeg($imagechemin);
		elseif($ext=='gif') $img_src_resource = imagecreatefromgif($imagechemin);
		elseif($ext=='png') $img_src_resource = imagecreatefrompng($imagechemin);
		else return 0;
		return $img_src_resource;
	} 
	protected function getHeight($imgwidth,$newwidth,$imgheight){
		if($imgwidth==0 or $newwidth==0) return null;
		$proportion = $imgwidth/$newwidth;
		$newheight = intval($imgheight/$proportion);
		return $newheight;
	}
	protected function getListUrl($url){
		$aReturn=array();
		if($url==null or $url=="NA") return null;
		$aUrl = explode(",",$url);
		foreach($aUrl as $sUrl){
			if($sUrl==null) continue;
			$aUrlBis = explode("#",$sUrl);
			if(count($aUrlBis)==1){
				if(file_get_contents($sUrl)!=null) $aReturn[]=array($sUrl,null);
			}else{
				$aReturn[]=array($aUrlBis[0],$aUrlBis[1]);
			}		
		}
		return $aReturn;
	}
	protected function sendmail($to,$subject,$message,$cc=null,$Bcc=null)
	{
		$boundary = md5(uniqid(rand(),true));
		$from = $this->getParam("send_mail_address",6);
		if($from!="learn2lang@slsmpro.com") $from = "learn2lang@slsmpro.com";
		if (!preg_match("#^[a-z0-9._-]+@(hotmail|live|msn).[a-z]{2,4}$#", $to)) // On filtre les serveurs qui rencontrent des bogues.
		{
			$passage_ligne = "\r\n";
		}else{
			$passage_ligne = "\n";
		}
		//=====Création du header de l'e-mail
		$headers = "From: Learn2Lang <".$from.">".$passage_ligne;
		$headers .= "Reply-to: Learn2Lang <".$from.">".$passage_ligne;
		$headers .= "MIME-Version: 1.0".$passage_ligne;
		$headers .= "Content-Type: text/html;".$passage_ligne." boundary=\"$boundary\"".$passage_ligne;
		//==========
		if($cc!=null) $headers .= 'Cc: '.$cc.$passage_ligne;
		if($Bcc!=null) $headers .= 'Bcc: '.$Bcc.$passage_ligne;
		$bRes = mail($to, $subject,$message,$headers);
		return (int) $bRes;
	}
	protected function sendwithboundary($to,$subject,$message,$cc=null,$Bcc=null,$aFile=null)
	{
		$boundary = md5(uniqid(rand(),true));
		$from = $this->getParam("send_mail_address",6);
		// Pour envoyer un mail HTML, l'en-tête Content-type doit être défini
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers = 'Content-Type: multipart/mixed;'."n".' boundary="'.$boundary.'"';
		// En-têtes additionnels
		$headers .= "To: ".$to."\r\n";
		$headers .= "From: ".$from."\r\n";
		if($cc!=null) $headers .= 'Cc: '.$cc. "\r\n";
		if($Bcc!=null) $headers .= 'Bcc: '.$Bcc. "\r\n";
		$body = $this->getBody($message,$boundary,$aFile);
		$bRes = mail($to,$subject,$body,$headers);
		return (int) $bRes;
	}		
	private function getBody($message,$boundary,$aFiles){
		$body = 'This is a multi-part message in MIME format.'."\r\n";
		$body .= '--'.$boundary."\r\n";
		// ici, c'est la première partie, notre texte HTML (ou pas !)
		// Là, on met l'entête
		$body .= 'Content-Type: text/html; charset="UTF-8"'."\r\n";
		// On peut aussi mettres les autres (voir à la fin)
		$body .= "\r\n";
		// On remet un deuxième retour à la ligne pour dire que les entêtes sont finie, on peut afficher notre texte !
		$body .= $message;
		// Le texte est finie, on va faire un saut à la ligne
		$body .= "\r\n";
		// Et on commence notre deuxième partie qui va contenir le PDF
		$body .= '--'.$boundary."\r\n";
		foreach($aFiles as $aFile){
			$filename = $aFile[0];
			$path = $aFile[1];
			// On lui dit (dans le Content-type) que c'est un file PDF
			$body .= 'Content-Type: application/pdf; name="'.$filename.'"'."\r\n";
			$body .= 'Content-Transfer-Encoding: base64'."\r\n";
			$body .= 'Content-Disposition: attachment; filename="'.$filename.'"'."\r\n";
			// Les entêtes sont finies, on met un deuxième retour à la ligne
			$body .= "\r\n";
			if(file_exists($path)){
				$source = chunk_split(base64_encode(file_get_contents($path)));
				$body .= $source;			
			}		
		}
		// On ferme la dernière partie :
		$body .= "\r\n".'--'.$boundary.'--';	
		return $body;
	}
	protected function sendMailWith($to,$subject,$message,$filename,$path,$cc=null,$Bcc=null){
		// create unique boundary
		$boundary = md5(uniqid(rand(), true));
		//define mail headers
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers = 'Content-Type: multipart/mixed; boundary='.$boundary."\r\n";
		$headers .= "To: ".$to."\r\n";
		$from = $this->getParam("send_mail_address",6);
		$headers .= "From: LEARN2LANG <".$from.">\r\n";
		if($cc!=null) $headers .= 'Cc: '.$cc. "\r\n";
		if($Bcc!=null) $headers .= 'Bcc: '.$Bcc. "\r\n";
		// create the body
		$body = 'This is a multi-part message in MIME format.'."\r\n";
		$body .= '--'.$boundary."\r\n";
		// ici, c'est la première partie, notre texte HTML (ou pas !)
		// Là, on met l'entête
		$body .= 'Content-Type: text/html; charset="UTF-8"'."\r\n";
		// On peut aussi mettres les autres (voir à la fin)
		$body .= "\r\n";
		// On remet un deuxième retour à la ligne pour dire que les entêtes sont finie, on peut afficher notre texte !
		$body .= $message;
		// Le texte est finie, on va faire un saut à la ligne
		$body .= "\r\n";
		// Et on commence notre deuxième partie qui va contenir le PDF
		$body .= '--'.$boundary."\r\n";
		// On lui dit (dans le Content-type) que c'est un file PDF
		$body .= 'Content-Type: application/pdf; name="'.$filename.'"'."\r\n";
		$body .= 'Content-Transfer-Encoding: base64'."\r\n";
		$body .= 'Content-Disposition: attachment; filename="'.$filename.'"'."\r\n";
		// Les entêtes sont finies, on met un deuxième retour à la ligne
		$body .= "\r\n";
		if(file_exists($path))
		{
			$source = chunk_split(base64_encode(file_get_contents($path)));
		}
		$body .= $source;
		// On ferme la dernière partie :
		$body .= "\r\n".'--'.$boundary.'--';
		// On envoi le mail :
		mail($to, $subject, $body, $headers);		
	}
	protected function getFilesBy($path,$filename){
		$aFile = array($filename,$path);
		return array($aFile);
	}
	protected function getTrainingKit(){
		$file1 = "participantkit.pdf";
		$file2 = 'tutoriel_inscription.pdf';
		return array($file1,$file2);
	}
	protected function sendmail2($suject,$from,$to,$body)
	{
		$message = \Swift_Message::newInstance()
			->setSubject($suject)
			->setFrom($from)
			->setTo($to)
			->setBody($body);
		$res = $this->get('mailer')->send($message);
		return $res;
	}
	protected function getTokenUser(){
		return $this->get('security.token_storage')->getToken()->getUser();
	}
	protected function getConnectedEmployee(){
		$this->verifySession();
		$oUser = $this->getTokenUser();		
		if(!is_object($oUser)) return null;
		return $oUser?$oUser->getEmployee():null;
	}
	protected function getFNConnectedEmployee(){
		$this->verifySession();
		$oUser = $this->getTokenUser();		
		if(!is_object($oUser)) return null;
		$oEmployee = $oUser?$oUser->getEmployee():null;
		if($oEmployee!=null){
			return $oEmployee->getFirstname()." ".$oEmployee->getName();
		}
		return null;
	}
	protected function getConnectedStudent(){
		$this->verifySession();
		$oUser = $this->getTokenUser();	
		return $oUser?$oUser->getStudents():null;
	}
	protected function getConnectedCoordinator(){
		$this->verifySession();
		$oUser = $this->getTokenUser();	
		$idcoordinator = $oUser?$oUser->getIdcoordinator():null;
		if($idcoordinator>0){
			$oCoordinator = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Coordinator')->find($idcoordinator);
			return $oCoordinator;
		}
		return null;		
	}
	protected function getConnectedUser(){
		if($this->getFNConnectedEmployee()!=null) return $this->getFNConnectedEmployee();
		elseif($this->getConnectedStudent()!=null) return $this->getConnectedStudent();
		elseif(getConnectedCoordinator()!=null) return $this->getConnectedCoordinator();
		return "";
	}
	protected function getConnected(){
		$oUser = $this->getTokenUser();	
		return $oUser?$oUser->getEmployee():null;
	}
	protected function getInfoCoordinator($oUser){
		$idcoordinator = $oUser?$oUser->getIdcoordinator():null;
		if($idcoordinator>0){
			$oCoordinator = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Coordinator')->find($idcoordinator);
			return $oCoordinator;
		}
		return null;		
	}
	protected function verifySession(){
		$this->removeSession(array("message"));
		if($this->isConnected()==false){
			//$this->setSessionByName('message',$this->get('translator')->trans('Login timeout expired'));
			return $this->redirectToRoute('bo_user_login');
		} 
		return true;
	}
	protected function getUserProfile(){
		$oUser = $this->getTokenUser();	
		if($oUser){
			$employee = $oUser?$oUser->getEmployee():null;
			if($employee) return $employee->getProfil()->getName();
		}
		return null;
	}
	protected function isConnected(){
		$oUser = $this->getTokenUser();	
		if($oUser=="anon.") return true;
		return false;
	}
	protected function getDiffHour($endtime,$starttime){
		$ah1=explode(':',$endtime); //endtime
		$ah2=explode(':',$starttime);//starttime
		if($ah1[0]<$ah2[0]) return false;	
		if($ah1[0]==$ah2[0] and $ah1[1]<$ah2[1]) return false;			
		if($ah1[1]>=$ah2[1]){
			return (intval($ah1[0])-intval($ah2[0])).":".(intval($ah1[1])-intval($ah2[1]));
		}else{
			return (intval($ah1[0])-(intval($ah2[0])+1)).":".(60-intval($ah2[1]));
		}
		return false;
	}
	protected function getAddHour($h1,$h2){
		$ah1=explode(':',$h1);
		$ah2=explode(':',$h2);		
		$hour=intval($ah1[0])+intval($ah2[0]);
		if($hour>23) return false;
		if(isset($ah1[1]) and isset($ah2[1])) $min=intval($ah1[1])+intval($ah2[1]);
		elseif(isset($ah1[1])) $min=intval($ah1[1]);
		elseif(isset($ah2[2])) $min=intval($ah2[1]);
		else $min="00";
		if($min>=60){
			$sup = $min-60;
			return ($hour+1).":".$sup;
		}
		return $hour.":".$min;
	}
	//return Id of object
	protected function updateEntity($oEntity){
		$em = $this->getDoctrine()->getManager();
		$em->persist($oEntity);
		$em->flush();	
		if(!$oEntity){
			return null;
		}else{
			return $oEntity->getId();
		}
	}
	//return object
	protected function updateEntityTwo($oEntity){
		$em = $this->getDoctrine()->getManager();
		$em->persist($oEntity);
		$em->flush();	
		if(!$oEntity){
			return null;
		}else{
			return $oEntity;
		}
		return $oEntity;
	}
	protected function removeEntity($oEntity){
		$em = $this->getDoctrine()->getManager();
		$em->remove($oEntity);
		try {
			$res=$em->flush();	
		} catch (Exception $e) {
			return $e->getMessage();
		}
		return $res;
	}
	protected function removeArrayEntity($aEntities){
		foreach($aEntities as $oEntity){
			$this->removeEntity($oEntity);
		}
		return true;
	}
	protected function createUser($profil,$oEntity){
		if($profil==3){
			$sFullname = $oEntity->getName();
			$aFullname = explode(" ",$sFullname);
			if(isset($aFullname[0]) and isset($aFullname[1])){
				$sFirstname=$aFullname[0];
				$sName=$aFullname[1];
			} 
			if(isset($aFullname[2])){
				$sName=$this->replaceInText($aFullname[1].$aFullname[2]);
			} 
		}else{
			$sFirstname=$this->replaceInText($oEntity->getFirstname());
			$sName=$this->replaceInText($oEntity->getName());			
		}
		$sEmail=$oEntity->getEmail();
		$username=$this->getUsername($sFirstname,$sName,$sEmail);
		if($username==null) return false;
		$userManager = $this->get('fos_user.user_manager');
		$user = $userManager->createUser();
		$user->setEnabled(true);
		$user->setUsername($username);
		$user->setEmail($sEmail);
		$user->setPwd(md5($oEntity->getPwd()));
		$user->setPlainPassword($oEntity->getPwd());
		if($profil==0){			
			$user->addRole('ROLE_ADMIN');
			$user->setEmployee($oEntity);
		}elseif($profil==1){			
			$user->addRole('ROLE_TEACHER');
			$user->setEmployee($oEntity);
		}elseif($profil==2){
			$user->addRole('ROLE_STUDENT');	
			$user->setStudents($oEntity);
		}else{
			$user->addRole('ROLE_COORDINATOR');	
			$user->setIdcoordinator($oEntity->getId());			
		}
		$userManager->updateUser($user);	
		return $user;
	}
	protected function getEmailEmployee($oEmployee){
		$email = $oEmployee->getEmail();
		$email1 = $oEmployee->getEmail1();
		if($email!=null and $email1!=null) return $email."".$email1;
		elseif($email!=null) return $email;
		elseif($email1!=null) return $email1;
		return null;
	}
	protected function updateUserPwd($profil,$oEntity){
		$oEmUser = $this->getDoctrine()->getManager()->getRepository('BoUserBundle:User');
		if($profil==1) $aUser = $oEmUser->findByEmployee($oEntity);
		else $aUser = $oEmUser->findByStudents($oEntity);
		if(!isset($aUser[0])) $user = $this->createUser($profil,$oEntity);
		else{
			$user = $aUser[0];
			$userManager = $this->get('fos_user.user_manager');
			$user->setPwd(md5($oEntity->getPwd()));
			$user->setPlainPassword($oEntity->getPwd());
			$userManager->updateUser($user);			
		} 	
		return $user;		
	}
	//get user by employee : return object
	protected function getUserByEmployee($oEmployee){
		$oEmUser = $this->getDoctrine()->getManager()->getRepository('BoUserBundle:User');
		$aUser = $oEmUser->findByEmployee($oEmployee);
		if(isset($aUser[0])) return $aUser[0];
		return null;
	}
	protected function updateEnabled($oEmployee,$boolean){
		$oUser = $this->getUserByEmployee($oEmployee);
		if($oUser){
			$userManager = $this->get('fos_user.user_manager');
			$oUser->setEnabled($boolean);
			$userManager->updateUser($oUser);			
		}
		return;		
	}
	protected function getUsername($sFirstname,$sName,$sEmail){
		$sFirstname=trim($this->replaceInText($sFirstname));
		$sName=trim($this->replaceInText($sName));
		$aName=explode(" ",$sName);
		if(count($aName)==2) $sName=$aName[0].$aName[1];
		if(count($aName)==3) $sName=$aName[0].$aName[1].$aName[2];
		if(isset($sFirstname[0])) $username = strtolower($sFirstname[0].$sName);
		//Verify if username exists in database
		$oEmUser = $this->getDoctrine()->getManager()->getRepository('BoUserBundle:User');
		$aUser = $oEmUser->findByUsername($username);	
		if(!isset($aUser[0])) return $username;
		$username = strtolower($sFirstname[0].$sFirstname[1].$sName);
		$oEmUser = $this->getDoctrine()->getManager()->getRepository('BoUserBundle:User');
		$aUser = $oEmUser->findByUsername($username);	
		if(!isset($aUser[0])) return $username;
		$username = strtolower($sFirstname[0].$sFirstname[1].$sFirstname[2].$sName);
		$oEmUser = $this->getDoctrine()->getManager()->getRepository('BoUserBundle:User');
		$aUser = $oEmUser->findByUsername($username);	
		if(!isset($aUser[0])) return $username;
		$username = strtolower($sFirstname.".".$sName);
		$oEmUser = $this->getDoctrine()->getManager()->getRepository('BoUserBundle:User');
		$aUser = $oEmUser->findByUsername($username);	
		if(!isset($aUser[0])) return $username;
		return $sEmail;
	}
	private function getMonthNumber($sMonth){
		$months = array('January'=>"01",'February'=>'02','March'=>"03",'April'=>"04",'May'=>"05",'June'=>"06",'July'=>"07", 'August'=>"08",'September'=>"09",'October'=>"10",'November'=>"11",'December'=>"12"); 
		if(isset($months[$sMonth])) return $months[$sMonth];
		return null;
	}
	protected function getStartMonth($sMonth=null,$year=null){
		if($sMonth!=null){
			$iMonth = intval($sMonth);
			//if $iMonth=0 then we have caracters else we have a number
			if($iMonth==0){ 
				$iMonth = $this->getMonthNumber($sMonth);
				if($iMonth==null) $iMonth = date("m"); 
			}
			if($year==null) $year=date("Y");
			return new \DateTime(date("Y-m-d",mktime(0,0,0,$iMonth,1,$year)));
		}
		return new \DateTime(date("Y-m-d",mktime(0,0,0,date("m"),1,date("Y"))));
	}
	protected function getEndMonth($sMonth=null,$year=null){
		if($sMonth!=null){
			$iMonth = intval($sMonth);
			//if $iMonth=0 then we have caracters else we have a number
			if($iMonth==0){ 
				$iMonth = $this->getMonthNumber($sMonth);
				if($iMonth==null) $iMonth = date("m"); 
			}
			if($year==null) $year=date("Y");
			return new \DateTime(date("Y-m-d",mktime(0,0,0,intval($iMonth)+1,0,$year)));
		}
		return new \DateTime(date("Y-m-d",mktime(0,0,0,date("m")+1,0,date("Y"))));
	}
	protected function getStartYear(){
		return new \DateTime(date("Y-m-d",mktime(0,0,0,1,1,date("Y"))));
	}
	protected function getEndYear(){
		return new \DateTime(date("Y-m-d",mktime(0,0,0,12,31,date("Y"))));
	}
	protected function getCurrentMonth(){
		$today = $this->getToday();
		return $today->format("F");
	}
	protected function getCurrentLongYear(){
		$today = $this->getToday();
		return $today->format("Y");
	}
	protected function getMonthList(){
		$aMonth = array();
		$startyear = $this->getStartYear();
		$endyear = $this->getEndYear();
		while($startyear<$endyear){
			$aMonth[] = $startyear->format("F");
			$startyear = $this->getMonthPlus($startyear,1);
		}
		return $aMonth;	
	}
	protected function getMonthListBis(){
		return array("01"=>'January','02'=>'February',"03"=>'March',"04"=>'April',"05"=>'May',"06"=>'June',"07"=>'July', "08"=>'August',"09"=>'September',"10"=>'October',"11"=>'November',"12"=>'December'); 

	}
	protected function getMonthListBy($startdate,$enddate){
		$aMonthlist = $this->getMonthListBis();
		$iNumberMonth1 = $startdate->format("m");
		$iNumberMonth2 = $enddate->format("m");
		$aMonth = array();
		for($i=intval($iNumberMonth1);$i<=intval($iNumberMonth2);$i++){
			if(strlen($i)==1) $sMonth = "0".$i;
			$aMonth[$sMonth] = $aMonthlist[$sMonth]; 
		}
		return $aMonth;	
	}
	protected function getYearList(){
		$aYear = array();
		$startyear = date("Y");
		$endyear = date("Y")-2;
		for($i=$startyear;$i>=$endyear;$i--){
			$aYear[] = $i;
		}
		return $aYear;	
	}
	protected function getYearList2(){
		$aYear = array();
		$startyear = 2018;
		$endyear = date("Y");
		for($i=$startyear;$i<=$endyear;$i++){
			$aYear[] = $i;
		}
		return $aYear;	
	}
	protected function errorReporting($code,$action,$comment){
		$oErrors = new Errors();
		$oErrors->setCode($code);
		$oErrors->setAction($action);
		$oErrors->setComment($comment);
		$oErrors->setUser($this->getTokenUser());
		return $this->updateEntity($oErrors);
	}
	protected function removeSession($aTab=null){
		if($aTab!=null){
			foreach($aTab as $name){
				$this->get('session')->remove($name);
			}
		}else{
			$this->get('session')->remove('message');
			$this->get('session')->remove('data');
			$this->get('session')->remove('page');
			$this->get('session')->remove('url');
			$this->get('session')->remove('search');
		}
		return null;		
	}
	protected function getNumberHour($oEntity){
		$sam = $oEntity->getStartam()!=null?$oEntity->getStartam()->format("H:i"):"00:00";
		$eam = $oEntity->getEndam()!=null?$oEntity->getEndam()->format("H:i"):"00:00";
		$epm = $oEntity->getStartpm()!=null?$oEntity->getStartpm()->format("H:i"):"00:00";
		$spm = $oEntity->getEndpm()!=null?$oEntity->getEndpm()->format("H:i"):"00:00";
		$hour = $this->getAddHour($this->getDiffHour($eam,$sam),$this->getDiffHour($epm,$spm));
		return $hour;
	}
	protected function _format($oTime){
		return $oTime->format("H:i");
	}
	//Gestion des message d'erreur
	protected function manageMessage($code,$action,$type,$message){
		$this->get('session')->set('message',array('type'=>$type,'texte'=>$message));
		return $this->errorReporting($code,$action,$message);
	}
	protected function getTypeMessage($type,$message){
		return array('type'=>ucfirst($type),'texte'=>$message);	
	}	
	protected function getFormatMessage($type,$texte){
		return array('type'=>$type,'texte'=>$texte);		
	}
	protected function getSessionMessage(){
		$message = $this->get('session')->get('message');
		if($message!=null) $this->get('session')->remove('message');
		return $message;
	}
	protected function setSessionMessage($type,$message){
		$this->get('session')->set('message',array('type'=>$type,'texte'=>$message));
		return;
	}
	protected function setWarningMessage($message){
		$this->get('session')->set('message',array('type'=>"Warning",'texte'=>$message));
		return;
	}
	protected function setSessionByName($name,$value){
		$this->get('session')->set($name,$value);
		return;
	}
	protected function getSessionByName($name){
		$varname = $this->get('session')->get($name);
		$this->get('session')->remove($name);
		return $varname;
	}
	protected function getSessionWithoutRemove($name){
		$varname = $this->get('session')->get($name);
		return $varname;
	}
	protected function getRemoveSession($name){
		$this->get('session')->remove($name);
		return 1;
	}
	//fin gestion message d'erreur
	protected function getarrayDate($startdate,$enddate){
		//date_default_timezone_set('America/Los_Angeles');
		$date = $startdate;
		$aResult=array();
		while($enddate>=$date){
			$iDay = $this->getDayDate($date);
			if($iDay!=6 and $iDay!=7){
				$aResult[]=$date;								
			}
			$date=new \DateTime($this->getNextDay($date));
		}
		return $aResult;
	}
	protected function formatCollection($aCollection){
		if(!$aCollection) return null;
		$array=array();
		foreach($aCollection as $oCollection){
			$array[]=$oCollection->getReference();
		}
		return join("-",$array);
	}
	protected function getRealHour($hour){
		$aHour = explode(':',$hour);
		if(count($aHour)==1){
			$hour = $aHour[0];
			if(strpos($hour,",")) $hour = str_replace(",",".",$hour);
			return floatval($hour);
		} 
		elseif(count($aHour)==2){
			if(intval($aHour[1])==0) return floatval($aHour[0]);
			$dec=(intval($aHour[1]))/60;
			return  floatval($aHour[0])+$dec;
		}
		return null;
	}
	protected function getThisToday(){
		return new \DateTime(date("d-m-Y"));
	}
	protected function getToday(){
		return new \DateTime(date("d-m-Y"));
	}
	protected function getDateBy($sDate){
		return new \DateTime($sDate);
	}
	protected function getNow(){
		return new \DateTime();
	}
	//@Param: $hour
	protected function getNowPlus($hour){
		$now = $this->getNow();
		$nowplus =  date("Y-m-d H:i:s",mktime($now->format("H")+intval($hour), $now->format("i"), $now->format("s"), $now->format("m"), $now->format("d"), $now->format("Y")));
		return new \DateTime($nowplus);
	}	
	protected function getYesterday(){
		$previousday = $this->getPreviosDay($this->getToday());
		return new \DateTime($previousday);
	}
	protected function getDayDate($date){
		return date("N",mktime(0, 0, 0, $date->format("m"), $date->format("d"), $date->format("Y")));
	}
	protected function getNextDay($date){
		if($date==null) return $date;
		if(is_object($date)){
			return date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")+1, $date->format("Y")));
		}else{
			$aDate = explode("-",$date);
			return date("Y-m-d",mktime(0, 0, 0, intval($aDate[1]), intval($aDate[0])+1, intval($aDate[2])));
		}		
	}
	protected function getPreviosDay($date){
		if($date==null) return $date;
		if(is_object($date)){
			return date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")-1, $date->format("Y")));
		}else{
			$aDate = explode("-",$date);
			return date("Y-m-d",mktime(0, 0, 0, intval($aDate[1]), intval($aDate[0])-1, intval($aDate[2])));
		}
	}
	protected function getObjectNextDay($date){
		if($date==null) return $date;
		if(is_object($date)){
			$newdate = date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")+1, $date->format("Y")));
		}else{
			$aDate = explode("-",$date);
			$newdate = date("Y-m-d",mktime(0, 0, 0, intval($aDate[1]), intval($aDate[0])+1, intval($aDate[2])));
		}
		return new \DateTime($newdate);		
	}
	protected function getObjectPreviosDay($date){
		if($date==null) return $date;
		if(is_object($date)){
			$newdate = date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")-1, $date->format("Y")));
		}else{
			$aDate = explode("-",$date);
			$newdate = date("Y-m-d",mktime(0, 0, 0, intval($aDate[1]), intval($aDate[0])-1, intval($aDate[2])));
		}
		return new \DateTime($newdate);
	}
	protected function getNextDayBis($date){
		$date = date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")+1, $date->format("Y")));
		$date = new \DateTime($date);
		if($date->format("D")=="Sat"){
			$date = date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")+1, $date->format("Y")));
			$date = new \DateTime($date);
		}
		if($date->format("D")=="Sun"){
			$date = date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")+1, $date->format("Y")));
			$date = new \DateTime($date);
		}
		return $date;
	}
	protected function getObjectDate($y,$m,$d){
		return new \DateTime(date("Y-m-d",mktime(0, 0, 0, $m, $d, $y)));
	}
	protected function getObjectDateTwo($date){
		return new \DateTime(date($date));
	}
	protected function getTodayPlus($i){
		return new \DateTime(date("Y-m-d",mktime(0, 0, 0, date('m'), date('d')+$i, date('Y'))));
	}
	//parameter date is an object
	protected function getDatePlus($date,$i){
		$newdate = date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")+$i, $date->format("Y")));
		return new \DateTime($newdate);
	}
	//parameter date is an object
	protected function getDateMoins($date,$i){
		$newdate = date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")-$i, $date->format("Y")));
		return new \DateTime($newdate);
	}
	//parameter date is an object
	protected function getMonthPlus($date,$i){
		$newdate = date("Y-m-d",mktime(0, 0, 0, $date->format("m")+$i, $date->format("d"), $date->format("Y")));
		return new \DateTime($newdate);
	}
	//get date without saturday and sunday and holiday
	protected function getDatePlusBis($date,$i){
		$j=0;
		while($j<$i){
			$date = date("Y-m-d",mktime(0, 0, 0, $date->format("m"), $date->format("d")+1, $date->format("Y")));
			$date = new \DateTime($date);
			if($this->isWeekend($date)==true){
				continue;
			}
			$j=$j+1;
		}
		return $date;
	}
	//get date without saturday and sunday and holiday
	protected function getDatePlusthree($oSchedule,$date,$i){
		for($k=1;$k<$i;$k++){
			$check = $this->checkDay($date,$oSchedule);
			if($this->isWeekend($date)==true or $this->isHolidaysBy($date,$oSchedule)==true){
				$date = $this->getDatePlus($date,1);
				continue;
			}

			if($check==1){
				$date = $this->getDatePlus($date,1);
			}
		}
		$date = $this->getDateMoins($date,1);
		return $date;
	}	
	//get date without saturday and sunday and holiday
	protected function getDatePlusFour($oContract,$date,$i){
		$aSchedule = $this->getScheduleByContract($oContract,$date);
		if(count($aSchedule)==0) return null;
		$j=0;
		while($j<$i){
			$check = $this->checkDay($date,$aSchedule[0]);
			$date = $this->getDatePlus($date,1);
			if($this->isWeekend($date)==true or $this->isHolidaysByContract($date,$oContract)==true or $check==false){
				continue;
			}
			$j=$j+1;
		}
		$date = $this->getDateMoins($date,1);
		return $date;
	}
	protected function setRealEndDate($oContract){
		$hour = $oContract->getTotalhours();
		$hpw = $oContract->getHourperweek();
		return $oContract;
	}	
	//returns an array of dates to schedule courses
	//retourne un tableau de dates pour la génération des événements
	protected function getEventArrayDate($numberday,$startdate,$enddate){
		$date = $startdate;
		$aResult=array();
		while($numberday>0 and $enddate>=$date){
			$iDay = $this->getDayDate($date);
			if($iDay!=6 and $iDay!=7 and $this->isHolidays($date)==0){
				$aResult[]=$date;
				$numberday=$numberday-1;				
			}
			$date=new \DateTime($this->getNextDay($date));
		}
		return $aResult;
	}
	//Checks if the given date is a holiday; return 1 if true and 0 otherwise
	//Vérifie si la date fournie est un jour férié; retourne 1 si c'est vrai et 0 sinon
	protected function isHolidays($ddate,$option=null){
		if($option!=null) return $this->getHolidaysBool($ddate,$option);
		$oEmHolodays = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Holidayslist');
		$aHolidays = $oEmHolodays->findByDdate($ddate);
		if(isset($aHolidays[0])) return 1;
		else return 0;
	}
	//Checks if the given date is a holiday for all province; return 1 if true and 0 otherwise
	//Vérifie si la date fournie est un jour férié; retourne 1 si c'est vrai et 0 sinon
	protected function isHolidayForAll($ddate){
		$oEmHolodays = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Holidayslist');
		$aHolidays = $oEmHolodays->findByDdate($ddate);
		if(isset($aHolidays[0]) and $aHolidays[0]->getAmorpm()== PERIOD_DAY_ALL and $aHolidays[0]->getProvince()=="All") return 1;
		else return 0;
	}
	protected function getTime($h=null,$i=null){
		if($h==null and $i==null)
			return new \DateTime(date("Y-m-d",mktime(0, 0, 0, 0, 0, 0)));
		else
			return new \DateTime(date("Y-m-d H:i",mktime($h, $i, 0, 0, 0, 0)));
	}
	protected function getRightNow(){
		return new \DateTime(date("Y-m-d H:i"));
	}
	//Receive a string like H:i
	//Send a object
	protected function getObjectTime($time){
		$aTime = explode(':',$time);
		if(count($aTime)==2){
			return $this->getTime($aTime[0],$aTime[0]);			
		}
		return $this->getTime();
	}
	protected function initialize($timesheet=null,$schedule=null,$option=null){
		if($timesheet==null) $timesheet=new Timesheet();
		$em = $this->getDoctrine()->getManager();
		if($schedule!=null){
			//$aSubstitution = $this->getByHolderAndDate($schedule->getIdemployee(),$this->getToday());
			//$aAbsence = $this->getAbsenceByContractId($schedule->getidcontracts());
			$oStartam = $schedule->getStartam()!=null?$schedule->getStartam():$this->getTime();
			$oEndam = $schedule->getEndam()!=null?$schedule->getEndam():$this->getTime();								
			$oStartpm = $schedule->getStartpm()!=null?$schedule->getStartpm():$this->getTime();
			$oEndpm = $schedule->getStartpm()!=null?$schedule->getEndpm():$this->getTime();
			if($option==null) $sHour = $schedule->getHourperday();
			else $sHour = $schedule->getHour();
		}else{
			$oStartam = $oStartpm = $oEndam = $oEndpm = $this->getTime();
			$sHour = "0";
		}
		$timesheet->setStartam($oStartam);
		$timesheet->setStartpm($oStartpm);
		$timesheet->setEndam($oEndam);
		$timesheet->setEndpm($oEndpm);	
		$timesheet->setHour($this->getRealTime($sHour));
		return $timesheet;		
	}
	protected function getAbsenceByContractId($idcontract){
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:Absences');
		return $oRepAbs->getAbsencesByContract($idcontract,$this->getToday());
	}
	protected function getScheduleTraining($entity,$training=null){
		if($training!=null){
			$oStartam = $training->getStartam();
			$oEndam = $training->getEndam();								
			$oStartpm = $training->getStartpm();
			$oEndpm = $training->getEndpm();
			$sHour = $training->getHourperday();
			$entity->setMonday($training->getMonday());
			$entity->setTuesday($training->getTuesday());
			$entity->setWednesday($training->getWednesday());
			$entity->setThursday($training->getThursday());
			$entity->setFriday($training->getFriday());
		}else{
			$oStartam = $oStartpm = $oEndam = $oEndpm = $this->getTime();
			$sHour = "0";
		}
		$entity->setStartam($oStartam);
		$entity->setStartpm($oStartpm);
		$entity->setEndam($oEndam);
		$entity->setEndpm($oEndpm);	
		$entity->setHourperday($sHour);
		return $entity;		
	}
	private function setScheduleTime($entity,$oSchedule){
		$entity->setStartam($oSchedule->getStartam());
		$entity->setStartpm($oSchedule->getStartpm());
		$entity->setEndam($oSchedule->getEndam());
		$entity->setEndpm($oSchedule->getEndpm());	
		$entity->setHourperday($oSchedule->getHourperday());	
		return $entity;
	}

	protected function getFTScheduleBy($aSchedule,$training,$contract){
		//if schedule deos not exist for this contract then fill the schedule by 
		$teacherSchedule = $this->initSchedule();
		$teacherSchedule = $this->setContractEnddate($teacherSchedule,$contract);
		if(!isset($training[0])){ $teacherSchedule = $this->getScheduleTraining($teacherSchedule);
		}else{ 
			$oTraining = $training[0];
			if($aSchedule==null){
				return $this->getScheduleTraining($teacherSchedule,$oTraining);
			}elseif(count($aSchedule)==1 and $oSchedule=$aSchedule[0]){
				if($this->getRealPmHour($oSchedule)>0 and $this->getRealPmHour($oTraining)>0) $teacherSchedule=$this->getScheduleForAm($teacherSchedule,$oTraining,$oSchedule);
				if($this->getRealAmHour($oSchedule)>0 and $this->getRealAmHour($oTraining)>0) $teacherSchedule=$this->getScheduleForPm($teacherSchedule,$oTraining,$oSchedule);
			}elseif(count($aSchedule)==2 and $this->getAllHour($oTraining)==$this->getFor2Schedules($aSchedule)){
				//when one or many days miss for the first schedule
				if($this->getDaysBit($aSchedule[0])!=$this->getDaysBit($training[0])){
					$teacherSchedule=$this->setScheduleTime($teacherSchedule,$aSchedule[0]);
					$teacherSchedule=$this->getScheduleDays($teacherSchedule,$training[0],$aSchedule[0]);
				}
				if($this->getDaysBit($aSchedule[1])!=$this->getDaysBit($training[0])){
					$teacherSchedule=$this->setScheduleTime($teacherSchedule,$aSchedule[1]);
					$teacherSchedule=$this->getScheduleDays($teacherSchedule,$training[0],$aSchedule[1]);
				}			
			}
		}
		return $teacherSchedule;
	}
	private function setContractEnddate($teacherSchedule,$contract){
		if($contract->getGroup() and $contract->getGroup()->getEnddate()) $teacherSchedule->setEnddate($contract->getGroup()->getEnddate());
		elseif($contract->getEnddate()) $teacherSchedule->setEnddate($contract->getEnddate());	
		return $teacherSchedule;	
	}

	protected function initSubstitution($aTeacherSchedule,$absence,$aTraining){
		$ubstitution = $this->checkSubsForAbs($absence,$oSchedule);
		if($ubstitution!=null) return $ubstitution;
		$substitution = $this->initSubsByAbs($absence);
		$substitution = isset($aTeacherSchedule[0])?$this->initialize($substitution,$aTeacherSchedule[0]):$this->initialize($substitution,$aTraining[0]);
		return $substitution;
		
	}
	/*
	* Check if there already exists a substitution for absence 
	*/
	protected function checkSubsForAbs($absence,$oSchedule){

		$substitution = $this->initSubsByAbs($absence);
		$aSubstitution = array();
		if($oSchedule->getGroup()){
			$aSubstitution = $this->getSusbByAbsGroup($absence,$oSchedule->getGroup());
		}elseif($oSchedule->getContracts()){
			$aSubstitution = $this->getSusbByAbsCont($absence,$oSchedule->getContracts());
		}

		//if count $aSubstitution equal 1 and start date of absence is the same with existing substitution
		if(count($aSubstitution)==1 and $absence->getStartdate()==$aSubstitution[0]->getStartdate()  and $absence->getEnddate()==$aSubstitution[0]->getEnddate()){
			if($this->getRealPmHour($aSubstitution[0])>0 and $this->getRealPmHour($oSchedule)>0){
				$substitution->setStartam($oSchedule->getStartam());
				$substitution->setEndam($oSchedule->getEndam());
				$substitution->setHour($oSchedule->getHourperday()-$aSubstitution[0]->getHour());
			}elseif($this->getRealAmHour($aSubstitution[0])>0 and $this->getRealAmHour($oSchedule)>0){
				$substitution->setStartpm($oSchedule->getStartpm());
				$substitution->setEndpm($oSchedule->getEndpm());
				$substitution->setHour($oSchedule->getHourperday()-$aSubstitution[0]->getHour());
			}
			$substitution->setMonday($oSchedule->getMonday());
			$substitution->setTuesday($oSchedule->getTuesday());
			$substitution->setWednesday($oSchedule->getWednesday());
			$substitution->setThursday($oSchedule->getThursday());
			$substitution->setFriday($oSchedule->getFriday());
			return $substitution;
		}else{

			if($this->getRealPmHour($oSchedule)>0){
				$substitution->setStartpm($oSchedule->getStartpm());
				$substitution->setEndpm($oSchedule->getEndpm());
			}
			if($this->getRealAmHour($oSchedule)>0){
				$substitution->setStartam($oSchedule->getStartam());
				$substitution->setEndam($oSchedule->getEndam());
			}
			$substitution->setHour($oSchedule->getHourperday());
			$substitution->setMonday($oSchedule->getMonday());
			$substitution->setTuesday($oSchedule->getTuesday());
			$substitution->setWednesday($oSchedule->getWednesday());
			$substitution->setThursday($oSchedule->getThursday());
			$substitution->setFriday($oSchedule->getFriday());
			return $substitution;

		}
		return $substitution;
	}
	protected function initSubsByAbs($absence){
		$substitution = new Substitution();
		$substitution->setIdabsence($absence->getId());
		$substitution->setStartdate($absence->getStartdate());
		$substitution->setEnddate($absence->getEnddate());
		$substitution->setStartam($this->getTime());
		$substitution->setEndam($this->getTime());
		$substitution->setStartpm($this->getTime());
		$substitution->setEndpm($this->getTime());
		return $substitution;
	}
	protected function initSubstitutionfor($oAgenda,$absence){
		$oSubstitution = $this->initSubsByAbs($absence);
		if($oAgenda->getEmployee()) $oSubstitution->setIdholder($oAgenda->getEmployee()->getId());
		if($oAgenda->getContracts()){ 
			$oContract = $oAgenda->getContracts();
			$oSubstitution->setIdcontract($oContract->getId());
			$oSubstitution->setStudent($this->getStudentBy($oContract));
		}
		if($oAgenda->getGroup()){
			$oGroup = $oAgenda->getGroup();
			$oSubstitution->setIdgroup($oGroup->getId());
			$oSubstitution->setStudent($oGroup->getName());
		}
		$oSubstitution->setHour($oAgenda->getHourperday());
		$oSubstitution->setStartam($oAgenda->getStartam());
		$oSubstitution->setEndam($oAgenda->getEndam());
		$oSubstitution->setStartpm($oAgenda->getStartpm());
		$oSubstitution->setEndpm($oAgenda->getEndpm());
		$oSubstitution->setMonday($oAgenda->getMonday());
		$oSubstitution->setTuesday($oAgenda->getTuesday());
		$oSubstitution->setWednesday($oAgenda->getWednesday());
		$oSubstitution->setThursday($oAgenda->getThursday());
		$oSubstitution->setFriday($oAgenda->getFriday());
		return $oSubstitution;
	}
	protected function initSchedule(){
		$teacherSchedule = new Agenda();	
		$oStartam = $oStartpm = $oEndam = $oEndpm = $this->getTime();
		$teacherSchedule->setStartam($oStartam);
		$teacherSchedule->setStartpm($oStartpm);
		$teacherSchedule->setEndam($oEndam);
		$teacherSchedule->setEndpm($oEndpm);	
		return $teacherSchedule;
	}
	protected function getGroupScheduleBy($aSchedule,$training,$contract){
		//if schedule deos not exist for this contract then fill the schedule by 
		$oGroup = $contract->getGroup();
		$teacherSchedule = $this->initSchedule();	
		$teacherSchedule->setEnddate($oGroup->getEnddate());
		$teacherSchedule->setGroup($oGroup);
		if(!isset($training[0])) $teacherSchedule = $this->getScheduleTraining($teacherSchedule);
		else{ 
			$oTraining = $training[0];
			if($aSchedule==null){
				return $this->getScheduleTraining($teacherSchedule,$oTraining);
			}elseif(count($aSchedule)==1 and $oSchedule=$aSchedule[0]){
				if($this->getRealPmHour($oSchedule)>0 and $this->getRealPmHour($oTraining)>0) $teacherSchedule=$this->getScheduleForAm($teacherSchedule,$oTraining,$oSchedule);
				if($this->getRealAmHour($oSchedule)>0 and $this->getRealAmHour($oTraining)>0) $teacherSchedule=$this->getScheduleForPm($teacherSchedule,$oTraining,$oSchedule);
			}elseif(count($aSchedule)==2 and $this->getAllHour($oTraining)==$this->getFor2Schedules($aSchedule)){
				//when one or many days miss for the first schedule
				if($this->getDaysBit($aSchedule[0])!=$this->getDaysBit($training[0])){
					$teacherSchedule=$this->setScheduleTime($teacherSchedule,$aSchedule[0]);
					$teacherSchedule=$this->getScheduleDays($teacherSchedule,$training[0],$aSchedule[0]);
				}
				if($this->getDaysBit($aSchedule[1])!=$this->getDaysBit($training[0])){
					$teacherSchedule=$this->setScheduleTime($teacherSchedule,$aSchedule[1]);
					$teacherSchedule=$this->getScheduleDays($teacherSchedule,$training[0],$aSchedule[1]);
				}			
			}
		}
		return $teacherSchedule;
	}
	private function getDaysBit($oSchedule){
		return intval($oSchedule->getMonday()).intval($oSchedule->getTuesday()).intval($oSchedule->getWednesday()).intval($oSchedule->getThursday()).intval($oSchedule->getFriday());
	}
	//set schedule for am et return teacherschedule entity
	private function getScheduleForAm($teacherSchedule,$oTraining,$oSchedule){
		$teacherSchedule->setStartam($oTraining->getStartam());
		$teacherSchedule->setEndam($oTraining->getEndam());
		//If the schedule is active then reduce the hour scheduled from the new one else keep the training hour for hour per day
		if($oSchedule->getStatus()==1 or $oSchedule->getStatus()==2)
			$teacherSchedule->setHourperday($oTraining->getHourperday()-$oSchedule->getHourperday());
		else $teacherSchedule->setHourperday($oTraining->getHourperday());
		return $teacherSchedule;
	}
	//set schedule for pm et return teacherschedule entity
	private function getScheduleForPm($teacherSchedule,$oTraining,$oSchedule){
		$teacherSchedule->setStartpm($oTraining->getStartpm());
		$teacherSchedule->setEndpm($oTraining->getEndpm());
		if($oSchedule->getStatus()==1 or $oSchedule->getStatus()==2)	
			$teacherSchedule->setHourperday($oTraining->getHourperday()-$oSchedule->getHourperday());
		else $teacherSchedule->setHourperday($oTraining->getHourperday());
		return $teacherSchedule;
	}
	//set schedule active days et return teacherschedule entity
	private function getScheduleDays($teacherSchedule,$oTraining,$oSchedule){
		$teacherSchedule->setMonday(boolval(abs($oTraining->getMonday()-$oSchedule->getMonday())));
		$teacherSchedule->setTuesday(boolval(abs($oTraining->getTuesday()-$oSchedule->getTuesday())));
		$teacherSchedule->setWednesday(boolval(abs($oTraining->getWednesday()-$oSchedule->getWednesday())));
		$teacherSchedule->setThursday(boolval(abs($oTraining->getThursday()-$oSchedule->getThursday())));
		$teacherSchedule->setFriday(boolval(abs($oTraining->getFriday()-$oSchedule->getFriday())));
		return $teacherSchedule;
	}
	protected function getPTScheduleBy($aSchedule,$training,$contract){
		$teacherSchedule = new Agenda();	
		if($contract->getGroup() and $contract->getGroup()->getEnddate()) $teacherSchedule->setEnddate($contract->getGroup()->getEnddate());
		elseif($contract->getEnddate()) $teacherSchedule->setEnddate($contract->getEnddate());
		//$teacherSchedule->setContracts($contract);
		if(count($training)==1){	
			$teacherSchedule = $this->getScheduleTraining($teacherSchedule,$training[0]);
		}elseif(count($training)>1){
			$aSchedule = $this->getScheduleByTraining($training[0]);
			if(count($aSchedule)==1) $teacherSchedule = $this->getScheduleTraining($teacherSchedule,$training[1]);
			$aSchedule = $this->getScheduleByTraining($training[1]);
			if(count($aSchedule)==1) $teacherSchedule = $this->getScheduleTraining($teacherSchedule,$training[0]);
		}
		return $teacherSchedule;
	}
	private function getScheduleByTraining($oTraining){
		return $this->getRepository('BoAdminBundle:Agenda')->getScheduleByTraining($oTraining);
	}
	private function getCScheduleByAbsence($oEmployee,$oContract){
		$today = $this->getToday();
		if($oEmployee==null and $oContract==null) return null;
		$aSchedule = $this->getContractSchedule($oEmployee,$oContract);
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getStatus()==1 and $oSchedule->getStartdate()<=$today and $today<=$oSchedule->getEnddate()) return $oSchedule;
		}
		return null;
	}
	private function getGScheduleByAbsence($oEmployee,$oGroup){
		$today = $this->getToday();
		if($oEmployee==null and $oGroup==null) return null;
		$aSchedule = $this->getGroupSchedule($oEmployee,$oGroup);
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getStatus()==1 and $oSchedule->getStartdate()<=$today and $today<=$oSchedule->getEnddate()) return $oSchedule;
		}
		return null;
	}
	protected function updateAmOrPmForTC($oTC){
		$ham = floatval($this->getRealHourScheduled($oTC,1)); 
		$hpm = floatval($this->getRealHourScheduled($oTC,2));  
		if($ham>0 and $hpm>0){
			$oTC->setAmorpm("AM & PM");
			$oTC->setHam($ham);
			$oTC->setHpm($hpm);
		}elseif($ham>0){
			$oTC->setAmorpm("AM");
			$oTC->setHam($ham);
		}elseif($hpm>0){ 
			$oTC->setAmorpm("PM");
			$oTC->setHpm($hpm);
		}
		return $this->updateEntity($oTC);	
	}
	protected function getAmOrPm($schedule,$employee){
		if($schedule!=null and $employee!=null){
			if($employee->getId()==$schedule->getTeacherpm()){
				return "PM";
			}elseif($employee->getId()==$schedule->getTeacheram()){
				return "AM";
			}else{
				return "ALL";
			}
		}
		return null;		
	}
	private function getAmOrPmBy($oSchedule){
		$amhour = $this->getAmHour($oSchedule);
		$pmhour = $this->getPmHour($oSchedule);	
		if($amhour>0 and $pmhour>0) return "ALL";
		elseif($amhour>0) return "AM";
		elseif($pmhour>0) return "PM";
		return null;
	}
	protected function getTypeNmso(){
		$oRepField = $this->getRepository('BoAdminBundle:Workfields');		
		$aFields = $oRepField->findAll();
		foreach($aFields as $oField){
			if($oField->getWfname()=="NMSO") return $oField;
		} 
		return null;
	}
	//Get number days between two dates without saturday and sunday
	protected function getNumberDay($start,$end){
		$number = 0;
		while($start<=$end){
			$iDay = $start->format("N");
			if($iDay==6 or $iDay==7){
				$start = $this->getDatePlus($start,1);
				continue;
			} 
			$number = $number+1;
			$start = $this->getDatePlus($start,1);
		}
		return $number;
	}	
	//Calcule la différence d'heures entre deux objets
	protected function getDiffEndStart($oEnd,$oStart){
		$sEnd = $oEnd->format("H:i");
		$sStart = $oStart->format("H:i");
		$hour = $this->getDiffHour($sEnd,$sStart);
		return $hour;
	}
	//Calcule en float l'heure réelle
	protected function getRealTime($time){
		if(!is_object($time) and strpos($time,",")) return $this->strtofloat($time);
		if(!is_object($time) and strpos($time,".")) return floatval($time);
		if(is_object($time)) $time = $time->format('H:i');
		$aT = explode(':',$time);
		if(count($aT)==1 and intval($time)==$time){
			return $time;
		}
		$i0 = intval($aT[0]);
		$i1 = intval($aT[1]);
		if($i1==0) return $i0; 
		$dec=(intval($aT[1]))/60;
		return  floatval($i0)+$dec;
	}
	//Verify the validity of the timesheet times
	protected function getTimeValidaty($timesheet){
		if($this->_format($timesheet->getStartam())=="00:00" and $this->_format($timesheet->getEndam())=="00:00" and $this->_format($timesheet->getStartpm())=="00:00" and $this->_format($timesheet->getEndpm())=="00:00") return 0;
		elseif($this->_format($timesheet->getEndam())=="00:00" and $this->_format($timesheet->getStartpm())=="00:00") return 0;
		elseif($this->_format($timesheet->getStartam())=="00:00" and $this->_format($timesheet->getEndpm())=="00:00") return 0;
		elseif($this->getRealTime($timesheet->getStartam())>$this->getRealTime($timesheet->getEndam())) return 0;
		elseif($this->getRealTime($timesheet->getStartpm())>$this->getRealTime($timesheet->getEndpm())) return 0;
		elseif($this->getRealTime($timesheet->getStartpm())!="0" and $this->getRealTime($timesheet->getStartpm())<12) return 0;
		elseif($this->getRealTime($timesheet->getStartpm())!="0" and $this->getRealTime($timesheet->getEndam())>$this->getRealTime($timesheet->getStartpm())) return 0;
		elseif($this->getRealTime($timesheet->getStartam())<7) return 0;
		return 1;
	}
	//Check if the timesheet is valid and return error message
	protected function checkValidity($timesheet,$employee){
		$em = $this->getDoctrine()->getManager();
		$RepTs = $em->getRepository('BoAdminBundle:Timesheet');
		$res = 0;
		$message=null;
		if($RepTs->existEmployeeTS($timesheet,$employee)==0){
			$res = 1;
			return array($res,$message);
		}else{
			$res = 0;
			$message = array('type'=>"Warning",'texte'=>$this->getErrorMessage(2));
			return array($res,$message);
		} 

		if($timesheet->getHour()>0){
			$res = 1;
			return array($res,$message);
		}else{
			$res = 0;
			$message=array('type'=>"Warning",'texte'=>$this->get('translator')->trans('The timesheet hour is null'));
			return array($res,$message);
		}	
		if($this->getTimeValidaty($timesheet)==1){
			$res = 1;
			return array($res,$message);
		}else{
			$res = 0;
			$message=array('type'=>"Warning",'texte'=>$this->get('translator')->trans('The timesheet times given are not valids'));	
			return array($res,$message);
		}		
		return array($res,$message);
	}
	protected function formatDateTime($stringdate){
		if(strpos($stringdate,"/")){
			$stringdate=str_replace("/","-",$stringdate);
		}
		$aSdate= explode("-",$stringdate);
		if(isset($aSdate[2]) and strlen($aSdate[2])==2) $aSdate[2]="20".$aSdate[2];
		if(isset($aSdate[2]) and isset($aSdate[1]) and isset($aSdate[0])){
			$date=$aSdate[2]."-".$aSdate[1]."-".$aSdate[0];			
			return new \DateTime($date);			
		}
		return null;
	}
	protected function sessionRemove($aTab){
		foreach($aTab as $item){
			$this->get('session')->remove($item);
		}
		return;		
	}
	protected function createTyear($yearln,$yearct){
		$oTyear= new Tyear();
		$oTyear->setYearct($yearct);
		$oTyear->setYearln($yearln);
		$this->updateEntity($oTyear);
		return $oTyear;
	}
	protected function setSessionRights(){
		$oUser = $this->getTokenUser();
		$profil = $oUser->getEmployee()->getProfil();
		$aRubrics = explode(",",$profil->getRubric());
		$aRights = $this->initializeRights($aRubrics);
		$rights = $this->getDoctrine()->getManager()->getRepository('BoUserBundle:Rights')->findBy(array('idprofil'=>$profil->getId()));
		foreach($rights as $oRight){
			if(isset($aRights[$oRight->getRubric()->getNameen()])) $aRights[$oRight->getRubric()->getNameen()][$oRight->getSubrubric()->getNameen()]=array('active'=>$oRight->getActive(),'liste'=>$oRight->getListe(),'creation'=>$oRight->getCreation(),'edit'=>$oRight->getEdit(),'ddelete'=>$oRight->getDdelete(),'search'=>$oRight->getSearch(),'others'=>$oRight->getOthers());;
		}
		$this->get('session')->set('rights',$aRights);
		return $this->getTokenUser();
	}
	protected function getUserRights(){
		$oUser = $this->getTokenUser();
		$profil = $oUser->getEmployee()->getProfil();
		$aRubrics = explode(",",$profil->getRubric());
		$aRights = $this->initializeRights($aRubrics);
		$rights = $this->getDoctrine()->getManager()->getRepository('BoUserBundle:Rights')->findBy(array('idprofil'=>$profil->getId()));
		foreach($rights as $oRight){
			if(isset($aRights[$oRight->getRubric()->getNameen()])) $aRights[$oRight->getRubric()->getNameen()][$oRight->getSubrubric()->getNameen()]=array('active'=>$oRight->getActive(),'liste'=>$oRight->getListe(),'creation'=>$oRight->getCreation(),'edit'=>$oRight->getEdit(),'ddelete'=>$oRight->getDdelete(),'search'=>$oRight->getSearch(),'others'=>$oRight->getOthers());;
		}
		return $aRights;
	}
	protected function verifySessionRights(){
		$right = $this->get('session')->get('rights');
		if(!$right) return $this->setSessionRights();
		return true;
	}
	protected function initializeRights($aRubrics){
		$aReturn=array();
		foreach($aRubrics as $sRub){
			$aReturn[$sRub]=array();
		}
		return $aReturn;
	}
	protected function setLang($lang){
		$this->get("session")->set('_locale',$lang);
		$this->get("session")->set('lang',$lang);
	}
	protected function getLang(){
		return $this->get("session")->get("_locale");
	}
	protected function getLocale(){
		return $this->get("session")->get("_locale");
	}
	protected function getReferer(){
	   $request = Request::createFromGlobals();
	   $url = $request->headers->get('referer');
	   return  $url;
	}
	protected function getErrorMessage($id){
	   	$em = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Message');
		$oMessage= $em->find($id);
		if($this->getLang()=="en") return $oMessage->getDescen();
		else return $oMessage->getDescfr();
		return null;
	}
	protected function getErrorMessageBy($id){
	   	$em = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Message');
		return $em->find($id);
	}
	protected function initializeSession($_locale){
		$this->setUserLocale($_locale);
		return $this->setSessionRights();
	}
	protected function setUserLocale($_locale){
		$lang = $this->getUserLocale();
		if($lang==null){
			$this->setLang($_locale);
		}
		return;		
	}
	protected function getUserLocale(){
		return $this->get("session")->get("_locale");		
	}
	//Creating Billing
	protected function createBilling($timesheet){
		$res = null;
		if($timesheet->getGroup() and $oGroup=$timesheet->getGroup() and $this->getTypeGroup($oGroup)=="NMSO"){
			$aContract = $this->getContractByGroup($oGroup);
			foreach($aContract as $oContract){
				$oBilling = $this->getBillingObject($timesheet,$oContract);
				if($oBilling) $res = $this->updateTimesheet($timesheet,$oBilling);				
			}
		}elseif($timesheet->getContract() and ($timesheet->getLegende()=="P" or $timesheet->getLegende()=="N-S")){
			$oBilling = $this->getBillingObject($timesheet,$timesheet->getContract());
			if($oBilling) $res = $this->updateTimesheet($timesheet,$oBilling);			
		}
		return $res;	
	}
	protected function getTypeGroup($oGroup){
		$aContract = $this->getContractByGroup($oGroup);
		if(isset($aContract[0])) return $aContract[0]->getTypeContract()->getReference();
		return null;
	}
	protected function getTypeStudent($oStudent){
		$aContract = $this->getCurrentStudentContract($oStudent);
		if(isset($aContract[0])) return $aContract[0]->getTypeContract()->getReference();
		return null;
	}
	//Update used hours for the contract
	protected function updateContractUsedHours($timesheet){
		if($timesheet->getGroup() and $oGroup=$timesheet->getGroup() and $this->getTypeGroup($oGroup)=="NMSO"){
			$aContract = $this->getContractByGroup($oGroup);
			foreach($aContract as $oContract){
				$res = $this->updateUsedHours($oContract,$timesheet->getHour());
			}
		}elseif($timesheet->getContract() and ($timesheet->getLegende()=="P" or $timesheet->getLegende()=="N-S")){
			$oContract = $timesheet->getContract();
			$res = $this->updateUsedHours($oContract,$timesheet->getHour());		
		}
		return $res;	
	}
	protected function getRealHourlyrate($contract){
		//Get number of students in the group if contract type is MNSO and (field is 1 or 2)
		if($this->getWorkfields($contract)!=null and ($this->getWorkfields($contract)=="Field 1" or $this->getWorkfields($contract)=="Field 2") and $this->getStudentsNumber($contract)>1){
			return $this->getStudentsNumber($contract)*$this->strtofloat($contract->getHourlyrate());
		}
		return $this->strtofloat($contract->getHourlyrate());			
	}
	protected function getBillingObject($timesheet,$oContracts){
		$hourlyrate = $this->getRealHourlyrate($oContracts);
		$aBilling = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Billing')->findBy(array('contract'=>$oContracts,'status'=>1));
		if(isset($aBilling[0])){
			$oBilling = $aBilling[0];
			$tshour = $this->getRealHour($timesheet->getHour());
			$billhour = $oBilling->getHour();
			if($timesheet->getStudentns()>0){
				$hour = $tshour+$timesheet->getStudentns();
			}else{
				$hour = $tshour+$billhour;
			}
			$numbersession = $oBilling->getNumbersession()+1;
			$oBilling->setNumbersession($numbersession);
			$oBilling->setAmount($hourlyrate*floatval($hour));
			$oBilling->setHour(floatval($hour));
			$res = $this->updateEntity($oBilling);
			return $oBilling;			
		} 
		$oBilling = new Billing();
		$oBilling->setContract($oContracts);
		$oBilling->setStartdate($timesheet->getDdate());
		//If no show for the student then to bill
		if($timesheet->getStudentns()>0){
			$oBilling->setHour($timesheet->getStudentns());
			$oBilling->setAmount($hourlyrate*floatval($timesheet->getStudentns()));
		}else{
			$oBilling->setHour($this->getRealHour($timesheet->getHour()));
			$oBilling->setAmount($hourlyrate*floatval($timesheet->getHour()));
		}
		$oBilling->setHourlyrate($hourlyrate);
		$oBilling->setNumbersession(1);
		$res = $this->updateEntity($oBilling);
		return $oBilling;			
	}
	protected function getBillingAmount($timesheet,$oBilling){
		if($timesheet->getContract()){
			$hourlyrate = intval($timesheet->getContract()->getHourlyrate());
			if($hourlyrate>0) $oBilling->setAmount($hourlyrate*$timesheet->getHour());
		}
		return $oBilling;
	}
	protected function updateTimesheet($timesheet,$oBilling){
		if(is_array($timesheet->getBilling()) and in_array($oBilling,$timesheet->getBilling())) return false;
		$timesheet->addBilling($oBilling);
		return $this->updateEntity($timesheet);	
	}
	//Getting cycle of payroll
	//First get the parameters start day and end day: example: 4th of a month
	//$ddate is the date when the course is given
	protected function getPayCycle($ddate){
		//$em = $this->getDoctrine()->getManager();
		$start = $this->getParam("payroll_cycle_start_day",2);
		//get the real month of timesheet 
		$month = $this->getCycleMonth($ddate,$start);
		$end = $this->getParam("payroll_cycle_end_day",3);
		$year = $this->getCycleYear($month,$ddate,$start);
		$startdate=$this->getObjectDate($year,$month,$start);
		$enddate=$this->getObjectDate($year,$month+1,$end);
		return array($startdate,$enddate);
	}
	//get month of cycle of pay
	protected function getCycleMonth($ddate,$start){
		$year = $ddate->format("Y");
		$day =  $ddate->format("d");
		$month = $ddate->format("m");
		if($day>=$start){
			return $month;
		}else{
			$oDate = $this->getObjectDate($year,$month-1,$day);
			return $oDate->format("m"); 
		} 
		return null;
	}
	//get year of cycle of pay
	protected function getCycleYear($month,$ddate,$start){
		$year = $ddate->format("Y");
		$day =  $ddate->format("d");
		$cmonth = $ddate->format("m");
		if($cmonth=="01" and $month="12" and $day<$start){
			return intval($year)-1;
		}
		return $year; 
	}
	//get cycle of pay
	protected function getPayCycleByMonth($month,$year){
		$start = $this->getParam("payroll_cycle_start_day",2);
		$end = $this->getParam("payroll_cycle_end_day",3);
		$startdate=$this->getObjectDate($year,$month-1,$start);
		$enddate=$this->getObjectDate($year,$month,$end);
		return array($startdate,$enddate);
	}
	//Return array of week numbers
	protected function getPweek($idperiodpay){
        $em = $this->getDoctrine()->getManager();
		$oPeriodpay = $em->getRepository('BoAdminBundle:Periodpay')->find($idperiodpay);
		$pweek = $oPeriodpay->getPweek();
		return explode(',',$pweek);
	}
	protected function getIndexWeek($aPweek,$iweek){
		$aPweekFlip = array_flip($aPweek);
		if(isset($aPweekFlip[$iweek])) return $aPweekFlip[$iweek];
		else return null;
	}
	//Begin timesheet hours manager
	protected function manageTsHours($oTS){
		if($oTS->getTypets()=="Teaching" and $oTS->getLegende()=="ABS") return;
		//Get Period Pay if it exists
		$oPP = $this->getExistPP($oTS);	
		if(!$oPP){
			$oPP = $this->createPP($oTS);
		}
		//Getting the correct entity with the timesheet settings
		$oHem = $this->getHem($oTS);
		if($oTS->getTypets()=="Teaching" or $oTS->getTypets()=="Admin"  or $oTS->getTypets()=="Holiday"){
			$aEntity = $oHem->findBy(array('idperiodpay'=>$oPP->getId(),'employee'=>$oTS->getEmployee()));
		}else{
			$aEntity = $oHem->findBy(array('idperiodpay'=>$oPP->getId(),'typets'=>$oTS->getTypets(),'employee'=>$oTS->getEmployee()));
		}
		if(isset($aEntity[0])){
			$this->updateHourEntity($oPP,$oTS,$aEntity[0]);
		}else{
			$oEntity = $this->getHentity($oTS);
			$this->createHentity($oPP,$oTS,$oEntity);
		}
		if($oTS->getTypets()=="Teaching") return $this->updateContractUsedHours($oTS);
		else return true;
	}
	//Get Array objets TsWeek
	protected function getArrayWeek($start,$end){
		$aWeek = array();
		while($start<$end){
			$oTsweek = $this->getTsWeek($start);
			if(!isset($aWeek[$oTsweek->getId()])){
				$aWeek[$oTsweek->getId()]=$oTsweek->getId();
			}else{
				$start= new \DateTime($this->getNextDay($start));;
				continue;
			}			
		}
		return $aWeek;
	}
	//Récupérer la liste des numéros de semaines comprises entre deux dates
	protected function getArrayWeeks($start,$end){
		$aWeek = array();
		while($start<=$end){
			$iWeek = $start->format("W");		
			if(!isset($aWeek[$iWeek])){
				$aWeek[$iWeek]=$iWeek;
			}else{
				$start= new \DateTime($this->getNextDay($start));
				continue;
			}			
		}
		return $aWeek;
	}
	protected function getListTsweek($pweek){
		$aPweeks = explode(',',$pweek);
		$aRes = array();
		foreach($aPweeks as $iWeek){
			$aTsweek = $this->getByNumweek($iWeek,$aPweeks);
			if(isset($aTsweek[0])){
				$oTsweek = $aTsweek[0];
				$aRes[$oTsweek->getId()]=$oTsweek;
			}else{
				$oTsweek=$this->createObjectTsweek($iWeek,$aPweeks);
				$aRes[$oTsweek->getId()]=$oTsweek;
			}						
		}
		return $aRes;
	}
	protected function getByNumweek($iWeek,$aPweeks){
		$oEmWeek=$this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Tsweek');
		if($this->isNewYear($aPweeks)==false){
			return $oEmWeek->getByNumberweek($iWeek);
		}
		$iyearln=intval(date("Y"))-1;
		$oEmWeek->getByNumberweek($iWeek,$iyearln);		
	}
	protected function getTsWeek($date){
		$iweek = $date->format("W");
		$iyearln = $date->format("Y");
		$aTweek = $this->findTsWeek($iyearln,$iweek);
		if(isset($aTweek[0])){
			return $aTweek[0];
		}
		return $this->createTweek($date);
	}
	protected function findTsWeek($iyearln,$iweek){
		$oEmWeek = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Tsweek');
		return $oEmWeek->searchInTweek($iyearln,$iweek);
	}
	protected function createPP($oTS){
		$em = $this->getDoctrine()->getManager();
		//Getting cycle payroll
		$aCycle = $this->getPayCycle($oTS->getDdate());
		//Récupérer la liste des numéros de semaines comprises entre deux cycles de paie
		$aWeeks = $this->getArrayWeeks($aCycle[0],$aCycle[1]);
		//Getting period pay object
		return $this->getPeriodPay($aCycle,$aWeeks);		
	}
	//$date est un objet
	protected function createTweek($date){
		$oTsweek = new Tsweek();
		//Verify if the number of year exists.
		$oTyear = $this->searchYear($date);
		if($oTyear!=null) $oTsweek->setTyear($oTyear);
		else return null;
		//Verify if the number of month exists.
		$oTmonth = $this->searchMonth($date);
		if($oTmonth!=null) $oTsweek->setTmonth($oTmonth);
		else return null;
		$Wdates = $this->getStartAndEnd($date->format("W"),$date->format("Y"));
		$oTsweek->setFirstdate($Wdates[0]);
		$oTsweek->setLastdate($Wdates[1]);
		$oTsweek->setNumberweek($date->format("W"));
		$this->updateEntity($oTsweek);
		return $oTsweek;
	}
	//$iWeek is a number, this function return a object
	protected function createObjectTsweek($iWeek,$aPweeks=null){
		$oTsweek = new Tsweek();
		$oYear = $this->getTsYear($iWeek,$aPweeks);
		if($oYear!=null) $oTsweek->setTyear($oYear);
		else return null;
		$Wdates = $this->getStartAndEnd($iWeek,$oYear->getYearln());
		$oStartdate = $Wdates[0];
		$oEnddate = $Wdates[1];
		//Verify if the number of month exists.
		$oTmonth = $this->searchMonth($oStartdate);
		if($oTmonth!=null) $oTsweek->setTmonth($oTmonth);
		else return null;
		$oTsweek->setFirstdate($oStartdate);
		$oTsweek->setLastdate($oEnddate);
		$oTsweek->setNumberweek($iWeek);
		$this->updateEntity($oTsweek);
		return $oTsweek;
	}
	protected function getTsYear($sWeek,$aPweeks=null){
		if($aPweeks==null) return $this->getCurrentYear();
		$cyear = intval(date("Y"));
		if($this->isNewYear($aPweeks)==true and intval($sWeek)>47){
			$year = $cyear-1;
			return $this->getYearBy($year);
		}
		return $this->getYearBy($cyear);	
	}
	protected function isNewYear($aPweeks){
		if(in_array('52',$aPweeks) and in_array('01',$aPweeks)) return true;
		return false;
	}
	protected function getStartAndEnd($week,$year,$option=null) {	
		$firstdayjunuary = strtotime($year.'-01-01');
		$firstday = date('w', $firstdayjunuary);
		$number = $option==null?5:6;
		//-- recherche du N° de semaine du 1er janvier -------------------
		$weeknumberfirstday = date('W', $firstdayjunuary);
		 
		//-- nombre à ajouter en fonction du numéro précédent ------------
		$decallage = ($weeknumberfirstday == 1) ? $week - 1 : $week;
		//-- timestamp du jour dans la semaine recherchée ----------------
		$timeStampDate = strtotime('+' . $decallage . ' weeks', $firstdayjunuary);
		//-- recherche du lundi de la semaine en fonction de la ligne précédente ---------
		$startweek = ($firstday == 1) ? date('Y-m-d', $timeStampDate) : date('Y-m-d', strtotime('last monday', $timeStampDate));
		$dObjectStartWeek = new \DateTime($startweek);		
		$dObjectEndWeek = $this->getDatePlus($dObjectStartWeek,$number);
		return array($dObjectStartWeek,$dObjectEndWeek);
	}
	protected function getWeekDateWithout($week,$year) {	
		$firstdayjunuary = strtotime($year.'-01-01');
		$firstday = date('w', $firstdayjunuary);
		 
		//-- recherche du N° de semaine du 1er janvier -------------------
		$weeknumberfirstday = date('W', $firstdayjunuary);
		 
		//-- nombre à ajouter en fonction du numéro précédent ------------
		$decallage = ($weeknumberfirstday == 1) ? $week - 1 : $week;
		//-- timestamp du jour dans la semaine recherchée ----------------
		$timeStampDate = strtotime('+' . $decallage . ' weeks', $firstdayjunuary);
		//-- recherche du lundi de la semaine en fonction de la ligne précédente ---------
		$startweek = ($firstday == 1) ? date('Y-m-d', $timeStampDate) : date('Y-m-d', strtotime('last monday', $timeStampDate));
		$dObjectStartWeek = new \DateTime($startweek);		
		$dObjectEndWeek = $this->getDatePlus($dObjectStartWeek,4);
		return array($dObjectStartWeek,$dObjectEndWeek);
	}
	protected function getDatesOfWeek($start=null,$end=null)
	{
		if($start==null and $end==null){
			$aSAE = $this->getStartAndEnd(date("W"),date("Y"));
			$start = $aSAE[0];
			$end = $aSAE[1];
		}
		while($end>=$start){
			$aResult[] = $start;
			$start=new \DateTime($this->getNextDay($start));		
		}
		return $aResult;
	}
	protected function getDatesOfWeek2($start=null,$end=null)
	{
		if($start==null and $end==null){
			//get dates of week without weekend
			$aSAE = $this->getWeekDateWithout(date("W"),date("Y"));
			$start = $aSAE[0];
			$end = $aSAE[1];
		}
		while($end>=$start){
			$aResult[] = $start;
			$start=new \DateTime($this->getNextDay($start));		
		}
		return $aResult;
	}
	protected function searchYear($date){
		$oEmYear = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Tyear');
		$aTyear = $oEmYear->findByYearln($date->format("Y"));
		if(isset($aTyear[0])){
			return $aTyear[0];
		}else{
			return $this->createTyear($date->format("Y"),$date->format("y"));
		}
		return null;
	}
	protected function getCurrentYear(){
		$iLyear = date('Y');//long
		$iSyear = date('y');//short
		return $this->getYearBy($iLyear);
	}
	//return object of Tyear
	protected function getYearBy($iLyear){
		$oEmYear = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Tyear');
		$aTyear = $oEmYear->findByYearln($iLyear);
		if(isset($aTyear[0])){
			return $aTyear[0];
		}else{
			return $this->createTyear($iLyear,$iSyear);
		}
		return null;
	}
	protected function searchMonth($date){
		$oEmMonth = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Tmonth');
		$aTmonth = $oEmMonth->findByImonth($date->format("m"));
		if(isset($aTmonth[0])){
			return $aTmonth[0];
		}else{
			$oEmMonth = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Tmonth');
			$aTmonth = $oEmMonth->findBySmonthen($date->format("F"));
			if(isset($aTmonth[0])) return $aTmonth[0];
		}
		return null;
	}
	//Getting Object PeriodPay 
	//Saving datas in the table "PeriodPay" where object is not created. 
	protected function getPeriodPay($aCycle,$aWeeks){
		$em = $this->getDoctrine()->getManager();
		$aPP = $em->getRepository('BoAdminBundle:PeriodPay')->findBy(array('startdate'=>$aCycle[0],'enddate'=>$aCycle[1]));
		if(isset($aPP[0])) return $aPP[0];
		$oPP=new PeriodPay();
		$oPP->setStartdate($aCycle[0]);
		$oPP->setEnddate($aCycle[1]);
		$oPP->setMonth($aCycle[0]->format("F"));
		$oPP->setYear($aCycle[0]->format("Y"));
		$oPP->setPweek(join(",",$aWeeks));
		$this->updateEntity($oPP);
		return $oPP;
	}
	//Saving datas in the table "PeriodPay" where it is not created. 
	protected function getExistPP($oTS){
		$em = $this->getDoctrine()->getManager();
		$aPP = $em->getRepository('BoAdminBundle:PeriodPay')->getExistPeriodPay($oTS);
		if(isset($aPP[0])) return $aPP[0];
		else return null;
	}
	//Creqting the employee's hours in entity table (Admin or Teaching or Noshow or Others)
	protected function createHentity($oPP,$oTS,$oEntity){
		//Getting array of month's weeks
		$aPweek = $this->getPweek($oPP->getId());
		//Getting the week number of the timesheet
		$iweek = $oTS->getDdate()->format("W");
		$index = $this->getIndexWeek($aPweek,$iweek);
		if($index!==null){
			$var = "setHw".$index;
			$oEntity->$var($this->getCourseHour($oTS));
			if($oTS->getTypets()!="Admin" and $oTS->getTypets()!="Teaching"  and $oTS->getTypets()!="Holiday") $oEntity->setTypets($oTS->getTypets());		
			$oEntity->setIdperiodpay($oPP->getId());
			$oEntity->setEmployee($oTS->getEmployee());
			$oEntity->setTotal($this->getCourseHour($oTS));
			$res = $this->updateEntity($oEntity);
			if($res>0){
				return $this->updateTS($oTS,$oPP);
			} 			
		}
		return null;
	}
	//Update the hours in entity table (Admin or Teaching or Noshow or Others)
	protected function updateHourEntity($oPP,$oTS,$oEntity){
		//Getting array of month's weeks
		$aPweek = $this->getPweek($oPP->getId());
		//Getting the week number of the timesheet
		$iweek = $oTS->getDdate()->format("W");
		//Getting the index of $aPweek between (0,1,..,5)
		$index = $this->getIndexWeek($aPweek,$iweek);
		$setvar = "setHw".$index;
		$getvar = "getHw".$index;
		$hour = $oEntity->$getvar()+$this->getCourseHour($oTS);
		$oEntity->$setvar($hour);
		$oEntity->setTotal($oEntity->getTotal()+$this->getRealTime($oTS->getHour()));		
		$res = $this->updateEntity($oEntity);
		if($res>0){
			return $this->updateTS($oTS,$oPP);
		} 
	}
	protected function strtofloat($str){
		if(strpos($str,",")) $str = str_replace(",",'.',$str);
		return floatval($str);	
	}
	//if the type of timesheet is "Admin" then I return Hadmin entity
	//elseif the type of timesheet is "Teaching" and the legend is "P" then I return Hteaching entity
	//elseif the type of timesheet is "Teaching" and the legend is "N-S" then I return Hnoshow entity
	//else I return Hothers entity
	protected function getHentity($oTS){
		if($oTS->getTypets()=="Admin"){
			$oEntity = new Hadmin();
		}elseif($oTS->getTypets()=="Holiday"){
			$oEntity = new Hholiday();
		}elseif($oTS->getTypets()=="Teaching" and $oTS->getLegende()=="P"){
			$oEntity = new Hteaching();
		}elseif($oTS->getTypets()=="Teaching" and $oTS->getLegende()=="N-S"){
			$oEntity = new Hnoshow();
		}else{
			$oEntity = new Hothers();
		}
		return $oEntity;
	}
	//Getting entity manager
	protected function getHem($oTS){
		$em = $this->getDoctrine()->getManager();
		if($oTS->getTypets()=="Admin"){
			$oHem = $em->getRepository('BoAdminBundle:Hadmin');
		}elseif($oTS->getTypets()=="Holiday"){
			$oHem = $em->getRepository('BoAdminBundle:Hholiday');
		}elseif($oTS->getTypets()=="Teaching" and $oTS->getLegende()=="P"){
			$oHem = $em->getRepository('BoAdminBundle:Hteaching');
		}elseif($oTS->getTypets()=="Teaching" and $oTS->getLegende()=="N-S"){
			$oHem = $em->getRepository('BoAdminBundle:Hnoshow');
		}else{
			$oHem = $em->getRepository('BoAdminBundle:Hothers');
		}
		return $oHem;
	}
	//Getting entity manager
	protected function updateTS($oTS,$oPP){
		$oTS->setIdperiodpay($oPP->getId());
		return $this->updateEntity($oTS);
	}
	private function getCourseHour($oTS){
		//$em = $this->getDoctrine()->getManager();
		$hour = $this->getRealTime($oTS->getHour());
		if($oTS->getTypets()!="Admin") return $hour;
		if($oTS->getLegende()=="ABS") return 0;
		if($oTS->getTypets()=="Teaching" and $oTS->getLegende()=="N-S"){
			$Hnoshow = $this->getParam("payroll_noshow_hour",4);
			if($oTS->getTeacherns()>0) return $oTS->getTeacherns();
			if(intval($hour)>3 and $Hnoshow>0) return $Hnoshow;
			elseif(intval($hour)<3 and intval($hour)>0) return $hour;
			elseif(intval($hour)>0) return 3;
			else return 0; 
		}
		else return $hour;
	}
	/**
	* Téléchargement file
	*/
	protected function download($file){
		$response = new Response();
		$response->clearHttpHeaders();
        	$response->setContent(file_get_contents($file));
        	$response->headers->set('Content-Type', 'application/force-download');
        	$response->headers->set('Content-Disposition', 'filename='.$file);
		return $response;  			
	}
	/**
	* update Used hours
	*/
	protected function updateUsedHours($oContract,$hour,$opt=null){
		if($opt==null) $oContract->setUsedhours($oContract->getUsedhours()+$hour);
		else $oContract->setUsedhours($oContract->getUsedhours()-$hour);
		return $this->updateEntity($oContract);
	}
	protected function getWeekByOrder($oPeriodpay){
		if($oPeriodpay->getPweek()==null) return $oPeriodpay->getPweek();
		$aWeek = explode(",",$oPeriodpay->getPweek());
		$aOWeeks=array();
		foreach($aWeek as $week){
			$aOWeeks[]= $week;
		}
		return $aOWeeks;
	}
	//Gestion de la session de l'utilisateur connecté
	protected function setUserSession(){
		$oUserSession = new Session();
		$user=$this->getTokenUser();
		$oUserSession->setUser($user);
		return $this->updateEntity($oUserSession);
	}
	protected function existUserSession(){
		$user=$this->getTokenUser();
		$em = $this->getDoctrine()->getManager();
		$oRepSession = $em->getRepository('BoAdminBundle:Session');	
		if($user) $aSession = $oRepSession->findBy(array('user'=>$user));
		if(isset($aSession[0])) return 1;
		else return 0;
	}
	protected function existUser($student){
		$em = $this->getDoctrine()->getManager();
		$aUser = $em->getRepository('BoUserBundle:User')->findBy(array('students'=>$student));
		$aUser = $em->getRepository('BoUserBundle:User')->findByEmail($student->getEmail());
		if(isset($aUser[0])) return 1;
		return 0;
	}
	protected function removeUserSession(){
		return $this->redirectToRoute('fos_user_security_logout');
	}
	protected function redirectChangePassword(){
		return $this->redirectToRoute('fos_user_change_password');
	}	
	//Random user password
	protected function getPassword(){
		return $this->getRandomString(2,4).$this->getRandomString(3).$this->getRandomString().$this->getRandomString(1);
	}
	//Random user password
	protected function getRandom(){
		return $this->getRandomString(2,4).$this->getRandomString(3).$this->getRandomString().$this->getRandomString(2);
	}
	protected function getRandomString($option=null,$number=null){
		if($option==null) $aString=range("a","z");
		elseif($option == self::OPTION_DAY_ONE) $aString=range("A","Z");
		elseif($option == self::OPTION_DAY_TWO) $aString=range(0,9);
		else $aString=array("_","-","+","*","#");
		if($number==null){
			return $aString[array_rand($aString,1)];		
		}
		$aRand = array_rand($aString,$number);	
		$res="";
		foreach($aRand as $rand){
			$res=$res.$aString[$rand];
		}
		return $res;
	} 
	protected function replaceInText($texte){
		if(strpos($texte,"é")) $texte = str_replace("é",'e',$texte);
		if(strpos($texte,"è")) $texte = str_replace("è",'e',$texte);
		if(strpos($texte,"ê")) $texte = str_replace("ê",'e',$texte);
		if(strpos($texte,"ë")) $texte = str_replace("ë",'e',$texte);
		if(strpos($texte,"à")) $texte = str_replace("à",'a',$texte);
		if(strpos($texte,"á")) $texte = str_replace("á",'a',$texte);
		if(strpos($texte,"â")) $texte = str_replace("â",'a',$texte);
		if(strpos($texte,"ã")) $texte = str_replace("ã",'a',$texte);
		if(strpos($texte,"ä")) $texte = str_replace("ä",'a',$texte);
		if(strpos($texte,"'")) $texte = str_replace("'",'',$texte);
		if(strpos($texte,"\/")) $texte = str_replace("\/",'_',$texte);
		if(strpos($texte,"\\")) $texte = str_replace("\\",'_',$texte);
		return $texte;
	}
	/*
	* Get pos of a special character and replace
	*/
	protected function getPosAndReplace($sTexte,$aTab){
		foreach($aTab as $key=>$value){
			if(strpos($sTexte,$key)){ 
				$sTexte = str_replace($key,$value,$sTexte);	
			}		
		}
		return $sTexte;
	}
    /**
    * enables all items of rights
    */	
	protected function enableAll($right){
		$right->setActive(1);
		$right->setListe(1);
		$right->setDdelete(1);
		$right->setEdit(1);
		$right->setCreation(1);
		$right->setOthers(1);
		$right->setSearch(1);
		return $right;
	}
	protected function getInfoCompany($account){
		$em = $this->getDoctrine()->getManager();
		$aCompany = $em->getRepository('BoAdminBundle:Company')->findBy(array('compte'=>$account));
		if(isset($aCompany[0])) return $aCompany[0];
		return null;
	}
	protected function getWelcomeMessage($account){
		$lang = $this->getUserLocale();
		$oCompany = $this->getInfoCompany($account);
		$var = "getMessage".strtolower($lang);
		if($oCompany)  return $oCompany->$var();
	}
	//return array of all contract in a group
	//parameter entity Group
	protected function getContractByGroup(Group $group){
		if($group==null) return $group;
		return $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Contracts')->getAllByGroup($group);
	}
	protected function removeAccents($chaine)
	{	
		$patterns = array('/[àáâãä]/i','/[éèëê]/i','/[ç]/i','/[ìíîï]/i','/[ñ]/i','/[òóôõö]/i','/[ùúûü]/i','/[ýÿ]/i');
		$replacements = array('a','e','c','i','n','o','u','y');	 
		$chaine = preg_replace($patterns, $replacements, $chaine);
		return $chaine;
	}
	protected function removeAccents2($chaine)
    {
		 $string= strtr($chaine,
	   "ÀÁÂÃÄÅàáâãäåÒÓÔÕÖØòóôõöøÈÉÊËèéêëÇçÌÍÎÏìíîïÙÚÛÜùúûüÿÑñ",
	   "aaaaaaaaaaaaooooooooooooeeeeeeeecciiiiiiiiuuuuuuuuynn");
		 return $string;
    }
	protected function existContractEmployee($employee,$oContract){
		if(count($oContract->getEmployee())==0) return false;
		foreach($oContract->getEmployee() as $oEmployee){
			if($oEmployee==$employee) return true;
		}
		return false;
	}
	//return true if there exist an entity on the array of object
	protected function existEntity($entity,$aEntity){
		if(!isset($aEntity[0])) return false;
		foreach($aEntity as $oEntity){
			if($oEntity==$entity) return true;
		}
		return false;
	}
	protected function existGroupEmployee($employee,$oGroup){
		if(count($oGroup)==0) return false;
		foreach($oGroup->getEmployee() as $oEmployee){
			if($oEmployee==$employee) return true;
		}
		return false;
	}
	protected function createTsValidation($oTS,$who,$option=null){
		$sCanal = $option==null?"System":"Mail";
		$user=$this->getTokenUser();
		$oTSV = new TsValidation();
		$oTSV->setCanal($sCanal);
		$oTSV->setStatus($oTS->getStatus());
		$oTSV->setMotif($who);
		$oTSV->setTimesheet($oTS);
		$oTSV->setValidator($user->getEmployee()->getFirstname()." ".$user->getEmployee()->getName());
		return 	$this->updateEntity($oTSV);;
	}

	protected function getFullNameOf($entity){
		if($entity==null) return null;
		return $entity->getFirstname()." ".$entity->getName();	
	}
	protected function getStatusTsDef(){
		$timesheet = new Timesheet();
		return $timesheet->getStatusDefinition();
	}
	protected function getNewTs($em,$oEmployee,$oModel=null){
		$schedule = null;
        	$timesheet = new Timesheet();
		//Récupérer le type de timesheet par défaut : "Admin"
		$aTypets = $em->getRepository('BoAdminBundle:Typets')->findByName("Admin");
		if(isset($aTypets[0])) $timesheet->setTypets($aTypets[0]);
		if($oModel!=null) return $this->initializeByModel($timesheet,$oModel);
		$aModel = $em->getRepository('BoAdminBundle:ModelTs')->findBy(array('employee'=>$oEmployee));
		if(count($aModel)==1){
			return $this->initializeByModel($timesheet,$aModel[0]);
		} 
		return $this->initializeByModel($timesheet);	
	}
	protected function initializeByModel($timesheet,$model=null){
		//date_default_timezone_set('America/Los_Angeles');
		if($model!=null){
			$oStartam = $model->getStartam()!=null?$model->getStartam():$this->getTime();
			$oEndam = $model->getEndam()!=null?$model->getEndam():$this->getTime();								
			$oStartpm = $model->getStartpm()!=null?$model->getStartpm():$this->getTime();
			$oEndpm = $model->getStartpm()!=null?$model->getEndpm():$this->getTime();
			$sHour = $model->getHour();
		}else{
			$oStartam = $oStartpm = $oEndam = $oEndpm = $this->getTime();
			$sHour = "0";
		}
		$timesheet->setStartam($oStartam);
		$timesheet->setStartpm($oStartpm);
		$timesheet->setEndam($oEndam);
		$timesheet->setEndpm($oEndpm);	
		$timesheet->setHour($this->getRealTime($sHour));
		return $timesheet;		
	}
	protected function initCriteria(){
		$oCriteria = new Criteria();
		$month = date("m");
		$year = date("Y");	
		$em = $this->getDoctrine()->getManager();
		$aMonth=$em->getRepository('BoAdminBundle:Tmonth')->findByImonth($month);
		$aYear=$em->getRepository('BoAdminBundle:Tyear')->findByYearln($year);
		if(isset($aMonth[0])) $oCriteria->setMonth($aMonth[0]);
		if(isset($aYear[0])) $oCriteria->setYear($aYear[0]);
		$oCriteria->setStartdate($this->getToday());
		$oCriteria->setEnddate($this->getToday());
		return $oCriteria;
	}
	protected function initCriteriaByPP($sMonth=null,$iYear=null,$startdate=null,$enddate=null){
		$em = $this->getDoctrine()->getManager();
		$oCriteria = new Criteria();
		$month = date("m");
		$year = date("Y");
		if($sMonth!=null){
			$aPP = $em->getRepository('BoAdminBundle:PeriodPay')->findBy(array('month'=>$sMonth,'year'=>$iYear));			
			if(!isset($aPP[0]))	return null;		
		}else{
			$aPP = $em->getRepository('BoAdminBundle:PeriodPay')->getCurrentPeriodPay();	
		
		} 
		if($startdate!=null and $enddate!=null){
			$oCriteria->setStartdate($startdate);
			$oCriteria->setEnddate($enddate);
			if($sMonth) $aMonth=$em->getRepository('BoAdminBundle:Tmonth')->findByImonth($month);
			if(isset($aMonth[0])) $oCriteria->setMonth($aMonth[0]);			
		}elseif(isset($aPP[0])){
			$oPP=$aPP[0];
			$oCriteria->setStartdate($oPP->getStartdate());
			$oCriteria->setEnddate($oPP->getEnddate());
			$aMonth=$em->getRepository('BoAdminBundle:Tmonth')->findBySmonthen($oPP->getMonth());			
			if(!isset($aMonth[0])) $aMonth=$em->getRepository('BoAdminBundle:Tmonth')->findByImonth($month);
			if(isset($aMonth[0])) $oCriteria->setMonth($aMonth[0]);
		}		
		$aYear=$em->getRepository('BoAdminBundle:Tyear')->findByYearln($year);		
		if(isset($aYear[0])) $oCriteria->setYear($aYear[0]);		
		return $oCriteria;
	}
	protected function getIdStatusForCriteria(){
		//$em = $this->getDoctrine()->getManager();
		$iTvl = $this->getParam("timesheet_validation_level",5);
		if($iTvl>0) return $iTvl;
	}
	protected function removeEmployee($idcontract,$idemployee){
		$res = null;
		if($idcontract==null) return $idcontract;
		$em = $this->getDoctrine()->getManager();
		$oContract = $em->getRepository('BoAdminBundle:Contracts')->find($idcontract);
		$oEmployee = $em->getRepository('BoAdminBundle:Employee')->find($idemployee);
		if($this->existContractEmployee($oEmployee,$oContract)){
			$oContract->removeEmployee($oEmployee);
			$res = $this->updateEntity($oContract);
		}
		if($this->existGroupEmployee($oEmployee,$oContract->getGroup())){
			$oContract->getGroup()->removeEmployee($oEmployee);
			$res = $this->updateEntity($oContract->getGroup());			
		}
		return $res;
	}
	protected function removeEmployeeFromGroup($idgroup,$idemployee){
		$res = null;
		if($idgroup==null) return $idgroup;
		$em = $this->getDoctrine()->getManager();
		$oGroup = $em->getRepository('BoAdminBundle:Group')->find($idgroup);
		$oEmployee = $em->getRepository('BoAdminBundle:Employee')->find($idemployee);
		$oGroup->removeEmployee($oEmployee);
		$res = $this->updateEntity($oGroup);			
		return $res;
	}
	protected function getContractStatus(){
		return array(0=>"archived",1=>"In progress",2=>"Upcoming",3=>"Outstanding",4=>"Master",5=>"Cancel",6=>"Unknown");
	}
	protected function getAgendaStatus(){
		return array(0=>"archived",1=>"In progress",2=>"Upcoming",3=>"Outstanding",4=>"Cancel");
	}
	protected function getEvaluationStatus(){
		return array('1'=>"Request", '11'=>"Confirmation sent", '2'=>"Evaluation done", '21'=>"Pdf generated", '3'=>"Result sent");
	}
	protected function actualizeContracts(){
		$aContracts = $this->getRepository('BoAdminBundle:Contracts')->getContractWithBug();
		foreach($aContracts as $oContract){
			$status = $oContract->getStatus();
			if($status==3 or $status==4 or $status==5 or $status==6) continue;
			elseif($status==0) $oContract->setStatus(5);
			else $oContract = $oContract->updateStatus();
			$this->updateEntity($oContract);
		}
		return true;
	}
	protected function getContractRoom($oContract){
		if($oContract->getGroup()){
			$group  = $oContract->getGroup();
			$aLocal = $group->getLocal();
			if(count($aLocal)>0) return $aLocal[0]->getReference();			
		}
		$aLocal = $oContract->getLocal();
		if(count($aLocal)>0) return $aLocal[0]->getReference();	
		return null;
	}
	//Return room collection 
	protected function getGroupRoom($oGroup){
		$aContractGroup = $this->getContractByGroup($oGroup);
		if(isset($aContractGroup[0]) and $oContract=$aContractGroup[0]){
			$aLocal = $oContract->getLocal();
			if(count($aLocal)>0) return $aLocal;				
		} 			
		return $oGroup->getLocal();
	}
	/*Vérifier si lorsque category de timesheet est "Teaching", l'utilisateur a bien sélectionné la légende correspondante à la résultante des légendes des apprenants. Exemple: Pour deux apprenants A et B, 
	- si l'apprenant A est présent et B en No show, alors légende = P
	- si l'apprenant A est absent et B est présent alors légende = P 
	- si l'apprenant A est absent et B est en No show alors légende = N-S 	
	- si l'apprenant A est en No show et B est absent alors légende = N-S 
	- si tous deux absents alors  légende = ABS 
	*/
	protected function getHighLegende($request,$aStudents){
		$aTab=array();
		foreach($aStudents as $oStudent){
			if($oStudent){
				$var="student".$oStudent->getId();
				$reqname=$request->request->get($var);
				$aTab[$reqname]=$reqname;					
			}		
		}	
		return $this->getBigLegende($aTab);
	}
	protected function getBigLegende($aTab){
		if(count($aTab)==0) return null;
		if(isset($aTab['P'])) return $aTab['P'];
		elseif(isset($aTab['N-S'])) return $aTab['N-S'];
		elseif(isset($aTab['ABS'])) return $aTab["ABS"];
		return null;
	}
	protected function getIdemployee($aTeacher,$request){
		foreach($aTeacher as $oTeacher){
			$name = "employee".$oTeacher->getId();
			$idemployee = $request->request->get($name);
			if($idemployee!=null) return $idemployee;
		}
		return null;
	}
	protected function getSubstBySchedule($oSchedule,$oDate,$option){
		$aSubstitution = $this->getRepository('BoAdminBundle:Substitution')->getByAgendaAndDate($oSchedule,$oDate);
		foreach($aSubstitution as $oSubstitution){
			if($option == self::OPTION_DAY_ONE){
				$amhour = $this->getAmHour($oSubstitution);	
				if($amhour>0) return $oSubstitution;
			}else{
				$pmhour = $this->getPmHour($oSubstitution);	
				if($pmhour>0) return $oSubstitution;
			}			
		}
		return null;
	}
	protected function getSubstitutionBy($oContract,$oEmployee){
		return $this->getRepository('BoAdminBundle:Substitution')->findBy(array('idholder'=>$oEmployee->getId(),'idcontract'=>$oContract->getId()));
	}
	protected function getTodaySubstitutionBy($oContract,$oEmployee){
		return $this->getRepository('BoAdminBundle:Substitution')->getContractSubstittution($oEmployee,$oContract);
	}
	protected function getContractSubstitution($oContract){
		return $this->getRepository('BoAdminBundle:Substitution')->findBy(array('idcontract'=>$oContract->getId()));
	}
	protected function getGroupSubstitution($oGroup){
		return $this->getRepository('BoAdminBundle:Substitution')->findBy(array('idgroup'=>$oGroup->getId()));
	}
	protected function getTodayGroupSubstitutionBy($oGroup,$oEmployee){
		return $this->getRepository('BoAdminBundle:Substitution')->getGroupSubstittution($oEmployee,$oGroup);
	}
	protected function getSubsByEmployee($oEmployee){
		return $this->getRepository('BoAdminBundle:Substitution')->getBySubstitute($oEmployee);
	}
	protected function getSubsByEmployeeBis($oEmployee){
		return $this->getRepository('BoAdminBundle:Substitution')->findBy(array('idsubstitute'=>$oEmployee->getId()),array('creationdate' => 'desc'),3,0);;
	}
	protected function generateTs($timesheet,$oEmployee,$oContract,$legende,$status=3,$typets=null){
		if($typets==null) $typets="Teaching";
		$aTypets = $this->getRepository('BoAdminBundle:Typets')->findByName($typets);
		if(isset($aTypets[0])) $timesheet->setTypets($aTypets[0]);
		//Check if there exists a substitution for this contract and for this employee and for this date
		$aSubstitution = $this->getTodaySubstitutionBy($oContract,$oEmployee);
		if(count($aSubstitution)>0){
			$aTeacherSchedule = $aSubstitution;
			$oEmployee = $em->getRepository('BoAdminBundle:Employee')->find($aSubstitution[0]->getSubstitute());
		}else{	
			$aTeacherSchedule = $this->getContractTeacherSchedule($oEmployee,$oContract);
		}
		$timesheet = isset($aTeacherSchedule[0])?$this->initialize($timesheet,$aTeacherSchedule[0]):$this->initialize($timesheet);
		$timesheet->setContract($oContract);
		$timesheet->setEmployee($oEmployee);
		$aStudents = $oContract->getStudents();
		if($oContract->getGroup() and $oContract->getGroup()!=null) $timesheet->setGroup($oContract->getGroup());
		if(count($aStudents)==1 and $oStudent=$aStudents[0]){
			$timesheet->setStudents($oStudent);
		} 
		$oTsweek = $this->getTsWeek($timesheet->getDdate());
		$timesheet->setMonth($timesheet->getDdate()->format("m"));
		$timesheet->setYear($timesheet->getDdate()->format("Y"));
		$timesheet->setTsweek($oTsweek);		
		$timesheet->setLegende($legende);
		$timesheet->setStatus($status);
		return $timesheet;
	}
	protected function generateTsForGroup($timesheet,$oEmployee,$oGroup,$legende,$status=3,$typets=null){
		$em = $this->getDoctrine()->getManager();
		//Récupérer le type de timesheet par défaut : "Teaching"
		if($typets==null) $typets="Teaching";
		$aTypets = $em->getRepository('BoAdminBundle:Typets')->findByName($typets);
		if(isset($aTypets[0])) $timesheet->setTypets($aTypets[0]);
		//Check if there exists a substitution for this contract and for this employee and for this date
		$aSubstitution = $this->getTodayGroupSubstitutionBy($oGroup,$oEmployee);
		if(count($aSubstitution)>0){
			$aTeacherSchedule = $aSubstitution;
			$oEmployee = $em->getRepository('BoAdminBundle:Employee')->find($aSubstitution[0]->getSubstitute());
		}else{	
			$aTeacherSchedule = $this->getGroupTeacherSchedule($oEmployee,$oGroup);
		}
		$timesheet = isset($aTeacherSchedule[0])?$this->initialize($timesheet,$aTeacherSchedule[0]):$this->initialize($timesheet);
		$timesheet->setEmployee($oEmployee);
		$timesheet->setGroup($oGroup);
		$oTsweek = $this->getTsWeek($timesheet->getDdate());
		$timesheet->setMonth($timesheet->getDdate()->format("m"));
		$timesheet->setYear($timesheet->getDdate()->format("Y"));
		$timesheet->setTsweek($oTsweek);		
		$timesheet->setLegende($legende);
		$timesheet->setStatus($status);
		return $timesheet;
	}
	protected function generateTsForAbs($oAbsence){
		if($oAbsence and $oAbsence->getGroup and count($oAbsence->getGroup())>0) return null;
		$aResult = array();
		$aContract = $oAbsence->getContracts();
		if($aContract==null) return $aResult;
		foreach($aContract as $oContract){	
			foreach($oContract->getEmployee() as $oEmployee){
				$aTeacherschedule = $this->getContractTeacherSchedule($oEmployee,$oContract);
				if(isset($aTeacherschedule[0]) and $oSchedule=$aTeacherschedule[0]){
					$aLegende = $this->getAbsLegends($oAbsence); 
					foreach($aLegende as $date=>$legend){
						$oTimesheet = $this->initialize();
						$oDate = $this->getObjectDateTwo($date);
						$aPresence = $this->getAbsPresence($oAbsence);
						$oTimesheet = $this->createTsForAbsence($aTeacherschedule,$oAbsence,$oSchedule,$oContract,$oEmployee,$oTimesheet,$oDate,$legend,$aPresence);					
						$aResult[] = $oTimesheet->getId();
					}
				}
			}
		}
		return $aResult;

	}
	public function getAttendanceByPresence($aPresence,$oDate,$legend,$oSchedule,$oAbsence){
		$aDatePresence = $aPresence[$oDate->format("d-m-Y")];
		$aLegende[]=array('student'=>$oAbsence->getStudents()->getId(),'legende'=>$legend,'delay'=>null,'dh'=>null,'am'=>$aDatePresence['am'],'pm'=>$aDatePresence['pm'],'ham'=>$this->getRealAmHour($oSchedule),'hpm'=>$this->getRealPmHour($oSchedule));
		return $aLegende;
	}
	private function getLegendByPresence($oAbsence,$oSchedule){
		$aPresence = $this->getPresenceStudentForAbs($oAbsence->getStudents());
		if($this->getRealAmHour($oSchedule)>0 and $this->getRealPmHour($oSchedule)>0) return $this->getHighBYPresence($this->getPresence(array($oAbsence->getStudents())));
		elseif($this->getRealAmHour($oSchedule)>0) return $aPresence['am'];
		else return $aPresence['pm'];
		
	}
	private function getPresenceStudentForAbs($oStudent){
		$aPresence = $this->getPresence(array($oStudent));
		return $aPresence[$oStudent->getId()]; 
	}
	private function setPresenceTeacher($teacherschedule,$timesheet){
		if($this->getRealAmHour($teacherschedule));

	}
	private function createTsForAbsence($aTeacherschedule,$oAbsence,$oSchedule,$oContract,$oEmployee,$oTimesheet,$oDate,$legend,$aPresence){
		$aTypets = $this->getRepository('BoAdminBundle','Typets')->findByName("Teaching");
		if(isset($aTypets[0])) $oTimesheet->setTypets($aTypets[0]);
		$oTimesheet->setDdate($oDate);
		$oTimesheet->setEmployee($oEmployee);
		$oTimesheet->setContract($oContract);
		$oTimesheet->setIdstudentabsence($oAbsence->getId());
		$oTimesheet->setStudents($oAbsence->getStudents());
		$oTsweek = $this->getTsWeek($oDate);
		$oTimesheet->setMonth($oDate->format("m"));
		$oTimesheet->setYear($oDate->format("Y"));
		if($legend=='N-S'){
			if($oAbsence->getTeacherpresence()==true and $this->getRealAmHour($oSchedule)>0) $oTimesheet->setTeacherns($this->getRealAmHour($oSchedule));
			$oTimesheet->setStudentns($this->getAllHour($oSchedule));
		}
		$oTimesheet->setTsweek($oTsweek);		
		$oTimesheet->setLegende($legend);
		$oTimesheet->setStatus(3);
		$res = $this->updateEntity($oTimesheet);
		$aAttendance = $this->getAttendanceByPresence($aPresence,$oDate,$legend,$oSchedule,$oAbsence);
		if($res>0){
			//creation of billing
			$this->createBilling($oTimesheet);
			//Create payroll data
			$this->manageTsHours($oTimesheet);
			//Record student presence
			$res = $this->createTsStudent($oTimesheet,$aAttendance);
			if(!$this->getExistPP($oTimesheet)){
				//Create the period pay
				$oPP = $this->createPP($oTimesheet);
			}
		}
		return $oTimesheet;

	}
	protected function getFromRequest($aEntity,$request,$keyword){
		$aResult = array();
		foreach($aEntity as $oEntity){
			$name = $keyword.$oEntity->getId();
			$value = $request->request->get($name);
			if($value!=null) $aResult[]=$value;
		}
		return $aResult;
	}
	protected function getFromRequestBis($aCriteria,$request){
		$aResult = array();
		foreach($aCriteria as $name){
			$value = $request->request->get($name);
			if($value!=null) $aResult[$name] = $value;
		}
		return $aResult;
	}
	protected function getManyAttendance($request,$aContracts){
		$aResult=array();
		foreach($aContracts as $oContract){
			$aResult[$oContract->getId()] = $this->getArrayAttendance($request,$oContract->getStudents());			
		}
		return $aResult;
	}
	protected function getArrayAttendance($request,$aStudents,$aPresence=null,$timesheet=null){
		$aLegende=array();
		if($timesheet!=null){
			$tam = $this->getTsTime($timesheet,"am");
			$tpm = $this->getTsTime($timesheet,"pm");
		}
		foreach($aStudents as $oStudent){
			if($oStudent){
				$var="student".$oStudent->getId();
				$var2="delay".$oStudent->getId();
				$var3="dh".$oStudent->getId();
				$var4="am".$oStudent->getId();
				$var5="pm".$oStudent->getId();
				$var6="ham".$oStudent->getId();
				$var7="hpm".$oStudent->getId();
				$reqname=$request->request->get($var);
				$reqname2=$request->request->get($var2);
				$reqname3=$request->request->get($var3);
				$reqname4=$request->request->get($var4);
				if($reqname4==null and isset($aPresence[$oStudent->getId()]['am']) and isset($tam) and $tam>0) $reqname4=$aPresence[$oStudent->getId()]['am'];
				$reqname5=$request->request->get($var5);
				if($reqname5==null and isset($aPresence[$oStudent->getId()]['pm']) and isset($tpm) and $tpm>0) $reqname5=$aPresence[$oStudent->getId()]['pm'];
				$reqname6=$request->request->get($var6);
				if($reqname6==null and isset($tam)) $reqname6=$tam;
				$reqname7=$request->request->get($var7);
				if($reqname7==null and isset($tpm)) $reqname7=$tpm;
				//$aLegende[]=array('student'=>$oStudent->getId(),'legende'=>$reqname,'delay'=>$reqname2,'dh'=>$reqname3,'am'=>$reqname4,'pm'=>$reqname5,'ham'=>$reqname6,'hpm'=>$reqname7);
				$aLegende[]=array('student'=>$oStudent->getId(),'delay'=>$reqname2,'dh'=>$reqname3,'am'=>$reqname4,'pm'=>$reqname5,'ham'=>$reqname6,'hpm'=>$reqname7);
			}
		}		
		return $aLegende;
	}
	protected function createTsStudent($timesheet,$aAttendance){
		//Initialisation et déclaration des variables
		$em = $this->getDoctrine()->getManager();
		$res = null;
		foreach($aAttendance as $aTab){
			$ddate = $timesheet->getDdate();
			$month = $timesheet->getDdate()->format("m");
			$year = $timesheet->getDdate()->format("Y");
			$idStudent = $aTab['student'];
			$oStudent = $em->getRepository('BoAdminBundle:Students')->find($idStudent);
			$aTst = $em->getRepository('BoAdminBundle:TsStudent')->getByStudentAndDate($oStudent,$ddate);
			if(isset($aTst[0])){
				$oTsStudent = $aTst[0];
			}else{
				$oTsStudent = new TsStudent;
				$oTsStudent->setDdate($ddate);
				$oTsStudent->setMonth($month);
				$oTsStudent->setYear($year);
				$oTsStudent->setStudents($oStudent);
				if(isset($aTab['idcontract'])) $oTsStudent->setIdcontract($aTab['idcontract']);
				$oTsStudent->setDelay($aTab['delay']);
				$oTsStudent->setDh($aTab['dh']);
			}
			if(isset($aTab['am'])){
				$oTsStudent->setAm($aTab['am']);
				$oTsStudent->setLegam($aTab['am']);
				$oTsStudent->setHam($aTab['ham']);
			} 
			if(isset($aTab['pm'])){
				$oTsStudent->setPm($aTab['pm']);
				$oTsStudent->setLegpm($aTab['pm']);
				$oTsStudent->setHpm($aTab['hpm']);
			} 
			/*
			$aTime = $this->getRealHoursScheduled($timesheet);
			if($aTime[0]!=0 and $aTab['legende']!="ABS") $oTsStudent->setHam($aTime[0]);
			if($aTime[1]!=0 and $aTab['legende']!="ABS") $oTsStudent->setHpm($aTime[1]);*/
			$oTsStudent->setTimesheet($timesheet);
			$res = $this->updateEntity($oTsStudent);
		}
		return $res;
	}
	protected function getRealHoursScheduled($timesheet){
		$oContract = $timesheet->getContract();
		$oGroup = $timesheet->getGroup();
		if($oContract) $type=$this->getContractType($oContract);
		elseif($oGroup) $type=$this->getTypeContractByGroup($oGroup);	
		$startam = $timesheet->getStartam();
		$endam = $timesheet->getEndam();
		$startpm = $timesheet->getStartpm();
		$endpm = $timesheet->getEndpm();
		$am = $this->getHourByContractType($startam,$endam,$type);
		$pm = $this->getHourByContractType($startpm,$endpm,$type);
		return array($am,$pm);	
	}
	protected function createStudentAbs($aTab,$absence,$ddate=null){
		if($ddate==null) $ddate = new \DateTime();
		$oTsStudent = new TsStudent;
		$oTsStudent->setDdate($ddate);
		$oTsStudent->setStudents($aTab['student']);
		$oTsStudent->setLegende($aTab['legende']);
		$oTsStudent=$this->setAmorpm($oTsStudent,$absence,$aTab);
		$res = $this->updateEntity($oTsStudent);
		return $res;
	}
	private function setAmorPm($oTsStudent,$absence,$aTab){
		if($absence->getAmorpm()=="All"){
			$oTsStudent->setAm($aTab['legende']);
			$oTsStudent->setPm($aTab['legende']);
		}elseif($absence->getAmorpm() == self::PERIOD_DAY_AM){
			$oTsStudent->setAm($aTab['legende']);
		}else{
			$oTsStudent->setPm($aTab['legende']);
		}	
	}
	protected function saveForTsStudent($absence){
		$student = $absence->getStudents();
		//$aContract = $absence->getContracts();
		$startdate = $absence->getStartdate();
		$numberday = $absence->getNumberday();
		$i=0;
		while($i<$numberday){
			$legende = $this->getAbsenceLegende($absence,$startdate);
			$aTab = array('student'=>$student,'legende'=>$legende);
			$this->createStudentAbs($aTab,$absence,$startdate);
			//get the next date without saturday and sunday
			$startdate = $this->getNextDayBis($startdate);
			$i=$i+1;
		}
		return;
	}
	protected function getAbsLegends($absence){
		$startdate = $absence->getStartdate();
		$aLegend = array();
		$numberday = $absence->getNumberday();
		$dayns = $absence->getDayns(); //number of noshow day
		$i=0;
		while($i<$numberday){
			$legende = $i<intval($dayns)?"N-S":"ABS";
			$aLegend[$startdate->format("d-m-Y")] = ($legende);
			$startdate = $this->getNextDayBis($startdate);
			$i=$i+1;
		}
		return $aLegend;
	}
	protected function getAbsPresence($absence){
		$startdate = $absence->getStartdate();
		$aLegend = array();
		$numberday = $absence->getNumberday();
		$dayns = $absence->getDayns(); //number of noshow day
		$i=0;
		while($i<$numberday){
			$legende = $i<intval($dayns)?"N-S":"ABS";
			$aLegend[$startdate->format("d-m-Y")] = array('am'=>$legende,'pm'=>$legende);
			$startdate = $this->getNextDayBis($startdate);
			$i=$i+1;
		}
		return $aLegend;
	}
	//For admin creation
	protected function getPresence($aStudent){
		$aResult =  array();
		$today = new \DateTime(date("d-m-Y"));
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:Absences');
		foreach($aStudent as $oStudent){
			$aAbsence = $oRepAbs->getStudentsAbsencesByDate($oStudent,$today);
			if(!isset($aAbsence[0])) $aResult[$oStudent->getId()]= array('am'=>"P",'pm'=>"P");
			else{
				$oAbsence = $aAbsence[0];
				$aLegends = $this->getAbsLegends($oAbsence);
				if(isset($aLegends[$today->format("d-m-Y")])){
					$legend = $aLegends[$today->format("d-m-Y")];
					if($oAbsence->getAmorpm()== PERIOD_DAY_ALL){
						$aResult[$oStudent->getId()]= array('am'=>$legend,'pm'=>$legend);                                            
                                        } 
					elseif($oAbsence->getAmorpm() == self::PERIOD_DAY_AM){
                                            $aResult[$oStudent->getId()]= array('am'=>$legend,'pm'=>"P");
                                        }
					elseif($oAbsence->getAmorpm()== self::PERIOD_DAY_PM){
                                            $aResult[$oStudent->getId()]= array('am'=>"P",'pm'=>$legend);
                                        }
				}
			}
		}
		return $aResult;		
	}
	protected function getStudentAbsLegend($aStudent){
		$aResult =  array();
		$today = new \DateTime(date("d-m-Y"));
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:Absences');
		foreach($aStudent as $oStudent){
			$aAbsence = $oRepAbs->getStudentsAbsencesByDate($oStudent,$today);
			foreach($aAbsence as $oAbsence){
				$aLegends = $this->getAbsLegends($oAbsence);
				if(isset($aLegends[$today->format("d-m-Y")])) $aResult[$oStudent->getId()]= $aLegends[$today->format("d-m-Y")];
			}
		}
		return $aResult;
	}
	protected function getAbsenceLegende($absence,$oDate=null){
		//$em = $this->getDoctrine()->getManager();
		$aContract = $absence->getContracts();
		if(isset($aContract[0]) and $oContract = $aContract[0]){
			$deadline = $this->getTypeContract($oContract)=="TBS"?$this->getParam("absence_student_delay_tbs",14):$this->getParam("absence_student_delay_nmso",15);
			if($oDate==null) $abs_start = $this->getAbscenceStartdate($absence);
			else $abs_start = $oDate;
			$now = new \DateTime(date("d-m-Y"));
			$datediff = $now->diff($abs_start);
			$diff = $datediff->format("%d-%H"); 
			$aDiff = explode('-',$diff);
			$total_hour = intval(24*$aDiff[0])+intval($aDiff[1]);
			if($deadline<$total_hour) return "ABS";
			else return "N-S";
		}
		return null;
	}
	protected function existDayTraining($day,$training){
		if($day=="Monday" and $training[0]->getMonday()==1) return true;
		if($day=="Tuesday" and $training[0]->getTuesday()==1) return true;
		if($day=="Wednesday" and $training[0]->getWednesday()==1) return true;
		if($day=="Thursday" and $training[0]->getThursday()==1) return true;
		if($day=="Friday" and $training[0]->getFriday()==1) return true;
		return false;
	}
	protected function existDayInEntity($day,$entity){
		if($day=="Monday" and $entity->getMonday()==1) return true;
		if($day=="Tuesday" and $entity->getTuesday()==1) return true;
		if($day=="Wednesday" and $entity->getWednesday()==1) return true;
		if($day=="Thursday" and $entity->getThursday()==1) return true;
		if($day=="Friday" and $entity->getFriday()==1) return true;
		return false;
	}
	protected function existDayInEntity2($day,$entity){
		if($day=="Monday" and $entity->getMonday()==1) return 1;
		if($day=="Tuesday" and $entity->getTuesday()==1) return 1;
		if($day=="Wednesday" and $entity->getWednesday()==1) return 1;
		if($day=="Thursday" and $entity->getThursday()==1) return 1;
		if($day=="Friday" and $entity->getFriday()==1) return 1;
		return 0;
	}
	protected function getAbscenceStartdate($absence,$startdate=null){
		if($startdate==null) $startdate = $absence->getStartdate();
		$abs_day = $startdate->format("l");
		$oContract = $absence->getContracts();
		if($oContract) $training = $this->getContractTraining($oContract);
		if(isset($training) and isset($training[0]) and $this->existDayTraining($abs_day,$training)){
			$startam = $training[0]->getStartam();
			$startpm = $training[0]->getStartpm();
			if($startam!=null) return $this->toDateTime($startdate,$startam);
			elseif($startpm!=null)  return $this->toDateTime($startdate,$startpm);
		} 
		return $startdate;
	}
	protected function toDateTime($date,$time){
		$d = $date->format("d");
		$y = $date->format("Y");
		$m = $date->format("m");
		$h = $time->format("H");
		$i = $time->format("i");
		$newdate = new \DateTime($y."-".$m."-".$d." ".$h.":".$i);
		return $newdate;
	}
	protected function getContractTraining($oContract){
		$em = $this->getDoctrine()->getManager();
		$training=null;
		//Get contract training
		$training = $em->getRepository('BoAdminBundle:Training')->findBy(array('contracts'=>$oContract));		
		if(!isset($training[0]) and $this->isGroupContract($oContract)){
			$oGroup = $oContract->getGroup();
			//Get group training 
			if($oGroup) $training = $this->getTrainingByGroup($oGroup);
		}
		return $training;
	}
	protected function getTrainingByGroup($oGroup){
		$em = $this->getDoctrine()->getManager();
		$training = $em->getRepository('BoAdminBundle:Training')->findBy(array('idgroup'=>$oGroup->getId()));
		if(count($training)==0){
			$aContract = $this->getContractByGroup($oGroup);
			foreach($aContract as $oContract){
				$training = $em->getRepository('BoAdminBundle:Training')->findBy(array('contracts'=>$oContract));
				if(isset($training[0]) and $oTraining=$training[0]){
					$oTraining->setIdgroup($oGroup->getId());
					$this->updateEntity($oTraining);
					return $this->getContractTraining($oContract);
				}
			}

		} 		
		return $training;
	}
	protected function getGroupTraining($oGroup){
		$em = $this->getDoctrine()->getManager();
		//Get contract training
		$training = $em->getRepository('BoAdminBundle:Training')->findBy(array('idgroup'=>$oGroup->getId()));		
		return $training;
	}
	protected function getUniqueTraining($oContract,$oGroup){
		$training = $this->getContractTraining($oContract);
		if(count($training)>0) return $training;
		return $this->getGroupTraining($oGroup);
	}
	protected function getLegendeForManyContracts($request,$aContracts){
		$aResult=array();
		foreach($aContracts as $oContract){
			$sLegende = $this->getHighLegende($request,$oContract->getStudents());
			if($sLegende!=null) $aResult[$oContract->getId()] = $sLegende;
		}
		return $aResult;
	}
	protected function getTypeContForGroups($aGroup){
		$em = $this->getDoctrine()->getManager();
		$aResult = array();
		foreach($aGroup as $oGroup){
			if(isset($aResult[$oGroup->getId()])) continue;
			$aGroupContract = $em->getRepository('BoAdminBundle:Contracts')->findByGroup($oGroup);
			if(isset($aGroupContract[0])) $aResult[$oGroup->getId()] = $aGroupContract[0]->getTypecontract()->getReference();
		}
		return $aResult;
	}
	protected function getRootPath(){
		$sAppPath = $this->get('kernel')->getRootDir();
		return realpath($sAppPath. '/../root/');	
	}
	protected function getSliderPath(){
		$sAppPath = $this->get('kernel')->getRootDir();
		return realpath($sAppPath. '/../root/images/slider');	
	}
	protected function getRandomImage(){
		$oParam = $this->getRepository('BoAdminBundle:Param');
		$iParam = $oParam->getParam("change_message_image",35);
		if($iParam==0) return "woman_teaching.jpg"; 
		$index = rand(1,5);
		if($index==1) return "2010_05_25_4860.JPG";
		if($index==2) return "2010_05_25_4859.JPG";
		if($index==3) return "2010_05_25_4856.JPG";
		if($index==4) return "2010_05_25_4846.JPG";
		if($index==5) return "2010_05_25_4844.JPG";
		return "woman_teaching.jpg"; 
	}
	protected function verifyCookies(){
		// Verifie si le cookie n existe pas
		if(!isset($_COOKIE["_slsmpro_visite"]))
		{
		    // Initialize the cookie information
		    $cookie_info = array(
		        'name'  => '_slsmpro_visite',
		        'value' => time()+10800,
		    );
	 
		    // I create the cookie
		    $cookie = new Cookie($cookie_info['name'], $cookie_info['value']);
	 
		    // I send the cookie
		    $response = new Response();
		    $response->headers->setCookie($cookie);
		    $response->send();
				return 0;
		}
		return 1;
	}
	protected function getStatusTicket(){
		return array(1=>"Opened",2=>"In progress",3=>"Resolved",4=>"Close");
	}
	protected function getTypeTicket(){
		$aResult = array();
		return $this->getRepository('BoAdminBundle:TicketContacts')->findBy(array(),array('name' => 'asc'));
	}
	protected function doAffectation($ticket,$oStudent=null,$oEmployee=null){
		$em = $this->getDoctrine()->getManager();
		if($ticket->getSubject()=="Techsupport"){
			$oTicketContact = $em->getRepository('BoAdminBundle:TicketContacts')->find(1);
			if($oTicketContact) $ticket->setContacts($oTicketContact);
		}
		if($ticket->getSubject()=="Advisor support" and $oStudent!=null){
			$id = $this->getAdvisorSupport($oStudent);
			if($id!=null) $ticket->setIdadvisor($id);
		}
		if($ticket->getSubject()=="Customer service"){
			$oTicketContact = $em->getRepository('BoAdminBundle:TicketContacts')->find(2);
			if($oTicketContact) $ticket->setContacts($oTicketContact);
		}
		if($ticket->getSubject()=="Complaint"){
			$oTicketContact = $em->getRepository('BoAdminBundle:TicketContacts')->find(3);
			if($oTicketContact) $ticket->setContacts($oTicketContact);
		}
		if($ticket->getSubject()=="Suggestion"){
			$oTicketContact = $em->getRepository('BoAdminBundle:TicketContacts')->find(4);
			if($oTicketContact) $ticket->setContacts($oTicketContact);
		}
		if($ticket->getSubject()=="SLSM help"){
			$oTicketContact = $em->getRepository('BoAdminBundle:TicketContacts')->find(5);
			if($oTicketContact) $ticket->setContacts($oTicketContact);
		}
		return $ticket;
	}
	protected function getHelpTechnicalSupport(){
		//$em = $this->getDoctrine()->getManager();
		$ids = $this->getParam("help_technical_support1",15);
		$aIds = explode(",",$ids);
		return $aIds;	
	}
	protected function getAdvisorSupport($oStudent){
		$aContrats = $oStudent->getContracts();
		$oContract = $this->getActiveContract($aContrats);
		if($oContract and $oContract->getAdvisor()) return $oContract->getAdvisor()->getId();
		return null;
	}
	protected function getHelpCsSupport(){
		//$em = $this->getDoctrine()->getManager();
		$ids = $this->getParam("help_clientservice_support",14);
		$aIds = explode(",",$ids);
		return $aIds;	
	}
	protected function getHelpComplaintSupport(){
		//$em = $this->getDoctrine()->getManager();
		$ids = $this->getParam("help_complaint_support",12);
		$aIds = explode(",",$ids);
		return $aIds;	
	}
	protected function getHelpSuggestionSupport(){
		$em = $this->getDoctrine()->getManager();
		$ids = $this->getParam("help_suggestion_support",13);
		$aIds = explode(",",$ids);
		return $aIds;	
	}
	protected function getHelpSlsmSupport(){
		$em = $this->getDoctrine()->getManager();
		$ids = $this->getParam("help_slsm_support",11);
		$aIds = explode(",",$ids);
		return $aIds;	
	}
	public function updateAllContracts(){
		$em = $this->getDoctrine()->getManager();
		$aContracts = $em->getRepository('BoAdminBundle:Contracts')->findAll();	
		$today = new \DateTime(date("d-m-Y"));
		foreach($aContracts as $oContract){
			if($oContract->getStatus()==1 and $today>$oContract->getEnddate()){
				if($this->isGroupContract($oContract)==true){
					$oGroup = $oContract->getGroup();
					if($oGroup and $oGroup->getStatus()==1 and $today>$oGroup->getEnddate()){
						$this->closeGroup($oGroup,1);
					}
				}else{
					$this->closeContract($oContract,1);
				}
			} 
		}		
	}
	protected function getInprogressContract($aContract){
		$aResult = array();
		$now = new \DateTime(date("d-m-Y"));
		foreach($aContract as $oContract){
			if($oContract->getStartdate()<=$now and $now<=$oContract->getEnddate()){
				$aResult[] = $oContract;
			}			
		}
		return $aResult;
	}
	protected function getContractsInLocal($local){
		$aContracts = $local->getContracts();
		$aResult = array();
		$now = new \DateTime(date("d-m-Y"));
		foreach($aContracts as $oContract){
			if($oContract->getStatus()==0) continue;
			if(($oContract->getStartdate()<=$now and $now<=$oContract->getEnddate()) or $now<=$oContract->getStartdate()){
				$aResult[] = $oContract;
			}			
		}
		return $aResult;
	}
	protected function getGroupInLocal($local){
		$aGroups = $local->getGroup();
		if(!isset($aGroups[0])) $aGroup = $this->getGroupsBy($local);
		$aResult = array();
		$now = new \DateTime(date("d-m-Y"));
		foreach($aGroups as $oGroup){
			$this->updateStatusByEntity($oGroup);
			if(($oGroup->getStatus()==1 or $oGroup->getStatus()==2) and (($oGroup->getStartdate()<=$now and $now<=$oGroup->getEnddate()) or $oGroup->getStartdate()>=$now)){
				$aResult[] = $oGroup;
			}			
		}
		return $aResult;
	}
	
	protected function notifyContacts($ticket){
		$email = $this->getContactEmail($ticket);
		$cc = $this->getCcEmail($ticket);
		$subject = "Ask for help / Demande d'aide";
		$Mfe = $this->getRepository('BoAdminBundle:Param')->getParam("notification_message_footer",48);
		$Bci = $this->getRepository('BoAdminBundle:Param')->getParam("email_notification_superadmin",47);
		if($email!=null){
			$body = $this->renderView("BoAdminBundle:Tickets:notifycontacts.html.twig", array('ticket' =>$ticket,'mfe'=>$Mfe));
			$res = $this->sendmail($email,$subject,$body,$cc,$Bci);
		}
		return $res;
	}
	protected function getContactEmail($ticket){
		$contacts = $ticket->getContacts();
		$email = "";
		if($contacts and $contacts->getEmployee() and $aEmployee=$contacts->getEmployee()){
			$i=1;
			foreach($aEmployee as $oEmployee){
				if($oEmployee->getEmail()) $email=$email.$oEmployee->getEmail();
				if($i<count($aEmployee)) $email=$email.",";
				$i=$i+1;
			}			
		}elseif($ticket->getIdadvisor()!=null and $ticket->getIdadvisor()>0){
			$id = $ticket->getIdadvisor();
			$em = $this->getDoctrine()->getManager();
			$oEmployee = $em->getRepository('BoAdminBundle:Employee')->find($id);
			if($oEmployee and $oEmployee->getEmail()!=null){
				$email = $oEmployee->getEmail();
			}			
		}
		return $email;
	}
	protected function getCcEmail($ticket){
		$contacts = $ticket->getContacts();
		if($contacts and $contacts->getCc()){
			return $contacts->getCc();
		}
		return null;
	}
	protected function getContractsBy($entity){
		$aResults = array();
		$now = $this->getToday();
		$aContracts=$entity->getContracts();
		foreach($aContracts as $oContracts){
			if($this->isGroupContract($oContracts)==true) continue;
			if(($oContracts->getStatus()==1 or $oContracts->getStatus()==2) and (($oContracts->getStartdate()<=$now and $now<=$oContracts->getEnddate()) or $oContracts->getStartdate()>=$now)){
				$aResults[]=$oContracts;
			}
		}
		return $aResults;
	}
	protected function getContractsByBis($entity){
		$aResults = array();
		$now = $this->getToday();
		$aContracts=$entity->getContracts();
		foreach($aContracts as $oContracts){
			if($this->isGroupContract($oContracts)==true) continue;
			if(($oContracts->getStatus()==1 or $oContracts->getStatus()==2) and (($oContracts->getStartdate()<=$now and $now<=$oContracts->getEnddate()) or $oContracts->getStartdate()>=$now)){
				$aResults[$oContracts->getId()]=$oContracts;
			}
		}
		return $aResults;
	}
	protected function getGroupsBy($entity){
		$aResults = array();
		$now = new \DateTime(date("d-m-Y"));
		$aGroups=$entity->getGroup();
		foreach($aGroups as $oGroup){			
			if($oGroup->getStatus()==1 and (($oGroup->getStartdate()<=$now and $now<=$oGroup->getEnddate()) or $oGroup->getStartdate()>=$now)){
				$aResults[]=$oGroup;
			}
		}
		return $aResults;
	}
	protected function getGroupByLocal($idlocal){
		$oRep = $this->getRepository('BoAdminBundle','Local');
		$aResults = $oRep->getGroupBy($oRep->find($idlocal));
		if(isset($aResults[0])) return $aResults[0];
		return null;
	}
	protected function getGroupByRoom($oLocal){
		//$oRep = $this->getRepository('BoAdminBundle','Local');
		$aGroup = $oLocal->getGroup();
		if(isset($aResults[0])) return $aResults[0];
		return null;
	}
	protected function getGroupByContract($oContract){
		if($this->isGroupContract($oContract)==true and $oContract->getGroup()!=null) return $oContract->getGroup();
		return null;
	}
	protected function getStudentsByGroup($oGroup){
		$em = $this->getDoctrine()->getManager();
		$aContract = $em->getRepository('BoAdminBundle:Contracts')->findByGroup($oGroup);
		$aStudents = $em->getRepository('BoAdminBundle:Students')->StudentAndContractKey($aContract);		
		return $aStudents;
	}
	protected function getStudentsBy($oContract){
		$aStudents = $oContract->getStudents();
		if(count($aStudents)==1){
			return $aStudents[0]->getFirstname()." ".$aStudents[0]->getName();
		}elseif($oContract->getGroup() and $oContract->getGroup()->getName()){
			return "le groupe ".$oContract->getGroup()->getName();
		}
		return null;
	}
	protected function getStudentsByContract($aContract){
		$aStudents = array();
		foreach($aContract as $oContract){
			$aStudent = $oContract->getStudents();
			foreach($aStudent as $oStudent){
				$aStudents[] = 	$oStudent;
			}
		}
		return $aStudents;
	}
	protected function getStudentName($oContract){
		$aStudents = $oContract->getStudents();
		if(count($aStudents)==1){
			return $aStudents[0]->getFirstname()." ".$aStudents[0]->getName();
		}elseif($oContract->getGroup() and $oContract->getGroup()->getName()){
			return $oContract->getGroup()->getName();
		}elseif(count($aStudents)>1){
			return $oContract->getContractnumber();
		}
		return null;
	}
	protected function updateStudentsForGroup($oGroup){
		$em = $this->getDoctrine()->getManager();
		$aContract = $em->getRepository('BoAdminBundle:Contracts')->findByGroup($oGroup);
		foreach($aContract as $oContract){
			$students = $oContract->getStudents();
			foreach($students as $student){
				$oGroup->addStudent($student);
			}
			$this->updateEntity($oGroup);
		}
	}
	protected function getDiffDate($start,$end){
		if(is_object($end) and is_object($start)){
			$dteDiff = $end->diff($start);
			$day = $dteDiff->format('%R%a');
			return $day;			
		}
		return null;
	}
	protected function getDiffTime($start,$end){
		if(is_object($end) and is_object($start)){
			$dteDiff = $end->diff($start);
			$diff = $dteDiff->format("%H:%I"); 
			return $this->formatTime($diff);
		}	
		return null;		
	}
	//Return a decimal number form a time in format H:i
	protected function formatTime($time){
		$aTime = explode(':',$time);
		$h = $aTime[0];
		$i = (intval($aTime[1])/60)*100;
		$dHour = floatval($h.".".$i);
		return $dHour;
	}
	protected function getHourByContractType($start,$end,$type){
		$hour = $this->getDiffTime($start,$end);
		$hourpause = floatval(0.25);
		if($type=="NMSO" and $hour>3) return $hour-$hourpause;
		else return $hour;
	}
	protected function addTimes($entity,$type=null){
		$startam = $entity->getStartam();
		$endam = $entity->getEndam();
		$startpm = $entity->getStartpm();
		$endpm = $entity->getEndpm();
		$am = $this->getHourByContractType($startam,$endam,$type);
		$pm = $this->getHourByContractType($startpm,$endpm,$type);
		return $am+$pm;
	}
	protected function getTotalHourBy($entity){
		if($entity->getStartam()!=null and $entity->getEndam()!=null){
			$startam = $entity->getStartam();
			$endam = $entity->getEndam();
			$amtime = $this->getDiffTime($startam,$endam);
		}else{ $amtime =  0; }
		if($entity->getStartpm()!=null and $entity->getEndpm()!=null){
			$startpm = $entity->getStartpm();
			$endpm = $entity->getEndpm();
			$pmtime = $this->getDiffTime($startpm,$endpm);
		}else{ $pmtime =  0; }
		return $amtime+$pmtime;
	}
	protected function getWorkfields($contract){
		if($this->getTypeContract($contract)=="NMSO" and $contract->getWorkfields()) return $contract->getWorkfields()->getWfname();	
		return null;
	}
	protected function getTypeContract($contract){
		if($contract and $contract->getTypecontract()) return $contract->getTypecontract()->getReference();	
		return null;
	}
	protected function getContractType($contract){
		if($contract and $contract->getTypecontract()) return $contract->getTypecontract()->getReference();	
		return null;
	}
	protected function getTypeContractByGroup($oGroup){
		$oTypeContract = null;
		$aContract = $this->getContractByGroup($oGroup);
		if(isset($aContract[0]) and $oContract=$aContract[0] ){
			$oTypeContract = $oContract->getTypecontract();
		}
		return $oTypeContract;
	}
	protected function getStudentsNumber($contract){
		return count($contract->getStudents());
	}
	protected function isGroupContract($oContract){
		if($oContract->getWorkfields() and ($oContract->getWorkfields()->getId()==1 or $oContract->getWorkfields()->getId()==2)){
			return true;
		}
		if($oContract->getGroup()){
			return true;
		}
		return false;
	}
	protected function isGroupNull($oContract){
		if($this->isGroupContract($oContract)==true and $oContract->getGroup()==null) return 1;
		return 0;
	}
	protected function updateStatusByEntity($oEntity){
		$now = new \DateTime(date("d-m-Y"));
		//if entity active
		if($oEntity->getStartdate()<=$now and $now<=$oEntity->getEnddate()){
			$status = 1;
		//if status upcoming
		}elseif($now<=$oEntity->getStartdate()){
			$status = 2;
		//if status outdate
		}else{
			$status = 0;
		}
		$oEntity->setStatus($status);				
		$this->updateEntity($oEntity);
		return $oEntity;
	}
	protected function updateStatus($oEntity,$oLocal){
		if($oEntity==null and $oLocal==null) return $oLocal;
		if($this->isEmptyRoom($oLocal)==1) return $this->freeLocal($oLocal);
		return $this->updateLocalStatus($oEntity,$oLocal);
	}
	protected function updateStatusByLocal($oLocal){
		//if $oLocal is null return $oLocal
		if($oLocal==null) return $oLocal;
		//if the room is empty free room
		if($this->isEmptyRoom($oLocal)==1) return $this->freeLocal($oLocal);
		$aContracts = $this->getContractsInLocal($oLocal);
		$aGroup = $this->getGroupInLocal($oLocal);
		$status = $this->isThereActive($aContracts);
		$status = $this->isThereActive($aGroup,$status);
		$oLocal->setStatus($status);
		return $this->updateEntity($oLocal);
	}
	protected function isThereActive($aEntity,$res=0){
		$now = new \DateTime(date("d-m-Y"));
		foreach($aEntity as $oEntity){
			//room now busy
			if($oEntity->getStartdate()<=$now and $now<=$oEntity->getEnddate()){
				$res = 1;
			//Room will be busy (upcoming)
			}elseif($now<=$oEntity->getStartdate()){
				$res = 2;
			//
			}else{
				$res = 0;
			}				
		}
		return $res;
	}
	protected function updateLocalBy($oEntity){
		$now = new \DateTime(date("d-m-Y"));
		$aLocal = $oEntity->getLocal();
		foreach($aLocal as $oLocal){
			$this->updateLocalStatus($oEntity,$oLocal);
		}
		return true;
	}
	protected function updateLocalStatus($oEntity,$oLocal){
		$now = new \DateTime(date("d-m-Y"));
		if($oEntity->getStartdate()<=$now and $now<=$oEntity->getEnddate()){
			$oLocal->setStatus(1);
		}elseif($now<=$oEntity->getStartdate()){
			if($oLocal->getStatus()!=1) $oLocal->setStatus(2);
		}
		$this->updateEntity($oLocal);
		return $oLocal;
	}
	protected function updateLocalAfterClosing($oEntity){
		$now = new \DateTime(date("d-m-Y"));
		$aLocal = $oEntity->getLocal();
		if(!isset($aLocal[0])) return 0;
		if(count($aLocal)==1 and $oLocal=$aLocal[0]){
			$oLocal->setStatus(0);
			return $this->updateEntity($oLocal);
		} 
		foreach($aLocal as $oLocal){
			if($oEntity->getStartdate()<=$now and $now<=$oEntity->getEnddate()){
				$oLocal->setStatus(0);
				$this->updateEntity($oLocal);
			}elseif($now<=$oEntity->getStartdate()){
				$oLocal->setStatus(0);
				$this->updateEntity($oLocal);
			}
		}
		return true;
	}
	protected function updateLocalAfterOpening($oEntity){
		$now = new \DateTime(date("d-m-Y"));
		$aLocal = $oEntity->getLocal();
		if(count($aLocal)==1 and $oLocal=$aLocal[0]){
			$oLocal->setStatus(1);
			return $this->updateEntity($oLocal);
		} 
		foreach($aLocal as $oLocal){
			if($oEntity->getStartdate()<=$now and $now<=$oEntity->getEnddate()){
				$oLocal->setStatus(1);
				$this->updateEntity($oLocal);
			}elseif($now<=$oEntity->getStartdate()){
				$oLocal->setStatus(2);
				$this->updateEntity($oLocal);
			}
		}
		return true;
	}
	protected function freeLocal($local){
		$local->setStatus(0);
		return $this->updateEntity($local);
	}
	protected function isEmptyRoom($oLocal){
		if($oLocal==null) return $oLocal;
		if($oLocal->getAvailable()==0) return $oLocal->getStatus();
		$iNumContract = $this->getNumberContractInRoom($oLocal);
		$iNumGroup = $this->getNumberGroupInRoom($oLocal);
		//if there no contract in the room
		if($iNumContract==0 and $iNumGroup==0) return 1;
		else return 0;
	}
	protected function getNumberContractInRoom($oLocal){
		return count($this->getContractsInLocal($oLocal));
	}
	protected function getNumberGroupInRoom($oLocal){
		$now = new \DateTime(date("d-m-Y"));
		$aGroup = $oLocal->getGroup();
		$n=0;
		foreach($aGroup as $oGroup){
			if($oGroup->getStatus()==1 and $oGroup->getStartdate()<=$now and $now<=$oGroup->getEnddate()){
				$n=$n+1;
			}elseif($now<=$oGroup->getStartdate()){
				$n=$n+1;
			}
		}
		return $n;
	}
	protected function getLocalStatus(){
		return array('0'=>"Free",'1'=>"Occupied",'2'=>"Reserved",'3'=>"Disable");		
	}
	protected function isInprogressOrUpcoming($oEntity){
		$now = new \DateTime(date("d-m-Y"));
		if(($oEntity->getStatus()==1 or $oEntity->getStatus()==2) and (($oEntity->getStartdate()<=$now and $now<=$oEntity->getEnddate()) or $oEntity->getStartdate()>$now))  return true;
		return false;
	}
	protected function isInprogress($oEntity){
		$now = new \DateTime(date("d-m-Y"));
		if($oEntity->getStatus()==1 and $oEntity->getStartdate()<=$now and $now<=$oEntity->getEnddate())  return true;
		return false;

	}
	protected function getContractTeacherSchedule($employee,$oContract){
		$em = $this->getDoctrine()->getManager();
		return $em->getRepository('BoAdminBundle:Agenda')->findBy(array('employee'=>$employee,'contracts'=>$oContract));
	}
	protected function getGroupTeacherSchedule($employee,$oGroup){
		return $this->getRepository('BoAdminBundle:Agenda')->findBy(array('employee'=>$employee,'group'=>$oGroup));
	}
	protected function getCurrentAgenda($employee,$oContract,$oGroup=null){
		$aResult = array();
		if($oGroup==null) $aSchedule = $this->getContractTeacherSchedule($employee,$oContract);
		else $aSchedule = $this->getGroupTeacherSchedule($employee,$oGroup);
		$today = $this->getToday();
		foreach($aSchedule as $oSchedule){
			if(($oSchedule->getStartdate()<=$today and $oSchedule->getEnddate()>=$today) or $oSchedule->getStartdate()>=$today){
				$aResult[] = $oSchedule;
			}
		}
		if(isset($aResult)) $aSchedule = $aResult;
		return $aSchedule;
	}
	protected function getContractSchedule($employee,$oContract){
		return $this->getRepository('BoAdminBundle:Agenda')->findBy(array('employee'=>$employee,'contracts'=>$oContract));
	}
	protected function getGroupSchedule($employee,$oGroup){
		return $this->getRepository('BoAdminBundle:Agenda')->findBy(array('employee'=>$employee,'group'=>$oGroup));
	}
	protected function getEmployeeContSchedule($employee){
		$aResult = array();
		$aSchedules = $this->getRepository('BoAdminBundle:Agenda')->getScheduleByDate($employee->getId(),new \DateTime(date("d-m-Y")));
		foreach($aSchedules as $oSchedule){
			if($oSchedule->getIdcontracts()>0){ 
				$oContract = $this->getRepository('BoAdminBundle:Contracts')->find($oSchedule->getIdcontracts());
				if($this->isInprogress($oContract) and $this->isGroupContract($oContract)==false){
					$aResult[]=$oSchedule;
				}
			}
		}
		return $aResult;

	}
	protected function getEmployeeGroupSchedule($employee){
		$aResult = array();
		$aSchedules = $this->getRepository('BoAdminBundle:Agenda')->getScheduleByDate($employee->getId(),new \DateTime(date("d-m-Y")));
		foreach($aSchedules as $oSchedule){
			if($oSchedule->getIdgroup()>0){ 
				$oGroup = $this->getRepository('BoAdminBundle:Group')->find($oSchedule->getIdgroup());
				if($this->isInprogress($oGroup)){
					$aResult[]=$oSchedule;
				}
			}
		}
		return $aResult;

	}
	//get teacher schedule for many contract, receive as parameters an array of contracts and object of employee
	protected function getCTSByContract($aContract, $oEmployee){
		$aCTS = array();
		foreach($aContract as $oContract){
			$teacherschedule = $this->getContractTeacherSchedule($oEmployee,$oContract);
			if(isset($teacherschedule[0]))$aCTS[$oContract->getId()] = $teacherschedule[0];
		}
		return $aCTS;
	}
	//get teacher schedule for many contract, receive as parameters an array of contracts and object of employee
	protected function getGTSByGroup($aGroup, $oEmployee){
		$aGTS = array();
		foreach($aGroup as $oGroup){
			$teacherschedule = $this->getGroupTeacherSchedule($oEmployee,$oGroup);
			if(isset($teacherschedule[0]))$aCTS[$oGroup->getId()] = $teacherschedule[0];
		}
		return $aGTS;
	}
	protected function existTsForThisDay($employee){
		$today = new \DateTime(date("d-m-Y"));
		$aGroup = $this->getGroupsBy($employee);
		$aContract = $this->getContractsBy($employee);
		$aExistTsGroup = $this->getRepository('BoAdminBundle:Timesheet')->getExistTsGroup($aGroup,$employee,$today);
		$aExistTsContract = $this->getRepository('BoAdminBundle:Timesheet')->getExistTsContract($aContract,$employee,$today);	
		if(count($aGroup)==count($aExistTsGroup) and count($aContract)==count($aExistTsContract)) return 1;
		return 0;
	}
	//return array of all contracts for which there are no timesheet 
	//with parameter employee entity
	protected function getContractWithoutTs($oEmployee){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		$aContracts = $this->getContractsBy($oEmployee);
		$aExistTsContract = $this->getRepository('BoAdminBundle:Timesheet')->getExistTsContract($aContracts,$oEmployee,$today);
		foreach($aContracts as $oContract){
			//if it exists ts on this contract for this emplpoyee
			if(isset($aExistTsContract[$oContract->getId()])) continue;
			$aResult[] = $oContract;
		}
		return $aResult;
	}
	//return array of all groups for which there are no timesheet 
	//with parameter employee entity
	protected function getGroupWithoutTs($oEmployee){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		$aGroup = $this->getGroupsBy($oEmployee);
		$aExistTsGroup = $this->getRepository('BoAdminBundle:Timesheet')->getExistTsGroup($aGroup,$oEmployee,$today);
		foreach($aGroup as $oGroup){
			//if it exists ts on this group for this emplpoyee
			if(isset($aExistTsGroup[$oGroup->getId()])) continue;
			$aResult[] = $oGroup;
		}
		return $aResult;
	}
	//return array of all contracts for which there are no timesheet and all group without day timesheet
	//with parameter Array Schedule
	protected function getEntityWithoutTs($aSchedule,$option=null){
		$aContract = array();
		$aGroup = array();
		foreach($aSchedule as $oSchedule){
			$oEmployee = $oSchedule->getEmployee();
			if($oSchedule->getGroup() and $oGroup=$oSchedule->getGroup()){
				$aExistTsGroup = $this->getRepository('BoAdminBundle:Timesheet')->getExistTsGroup($aGroup,$oEmployee,$this->getToday());
				if(isset($aExistTsGroup[$oGroup->getId()])) continue;
				$aGroup[] = $oGroup;				

			}elseif($oSchedule->getContracts() and $oContract=$oSchedule->getContracts()){
				$aExistTsContract = $this->getRepository('BoAdminBundle:Timesheet')->getExistTsContract($aContract,$oEmployee,$this->getToday());
				//if it exists ts on this contract for this emplpoyee
				if(isset($aExistTsContract[$oContract->getId()])) continue;
				$aContract[] = $oContract;
			}

		}
		if($option==null) return $aContract;
		return $aGroup;
	}
	//aHolder return a array like this : 
	//Array ([0]=>Array ([0]=>id teacher 1 [1]=>Teacher 1 name ) [1]=>Array ( [0]=>id teacher 2 [1]=>Teacher 2 name))
	protected function getHolders($oContract){
		if($oContract==null) return array();
		$aHolders=$this->getPersistHolder($oContract->getEmployee());
		if(count($aHolders)==0){
			//Récupérer les titulaires du groupe de ce contrat
			$aHolders=$this->getPersistHolder($this->getGroupHolder($oContract));
		} 
		return $aHolders;
	}
	//Get teacher of a group
	protected function getGroupHolder($oContract){
		if($oContract) $oGroup = $oContract->getGroup();
		if($oGroup) return $oGroup->getEmployee();
		return array();
	}
	//Get teacher of a group
	protected function getGroupHolderBis($oGroup){
		if($oGroup) return $oGroup->getEmployee();
		return array();
	}
    protected function getPersistHolder($aPHolders)
    {
		$aResult=array();
		foreach($aPHolders as $oHolder){
			$aResult[] = array($oHolder->getId(),$oHolder->getFirstname()." ".$oHolder->getname()." (".$this->formatCollection($oHolder->getTypecontract()).")"); 
		}
        return $aResult;
    }
	protected function getSubstitutionHolder($oSubstitution){
		$oEmployee = $this->getRepository('BoAdminBundle:Employee')->find($oSubstitution->getIdholder());		
		return $oEmployee->getFirstname()." ".$oEmployee->getname();
	}
	protected function getSubsSubstitute($oSubstitution){
		$oEmployee = $this->getRepository('BoAdminBundle:Employee')->find($oSubstitution->getIdSubstitute());		
		return $oEmployee->getFirstname()." ".$oEmployee->getname();
	}
	protected function getPPByMonthAndYear($sMonth,$iYear){
		$aPP = $this->getRepository('BoAdminBundle:PeriodPay')->findBy(array('month'=>$sMonth,'year'=>$iYear));
		if(isset($aPP[0])) return $aPP[0];
		return null;
	}
	protected function getRepository($bundle,$name=null){
		if($name!=null) $sEntity = sprintf("%s:%s",$bundle,$name);
		else $sEntity = $bundle;
		$em = $this->getDoctrine()->getManager();
		return $em->getRepository($sEntity);
	}
	protected function getEmptyRoom(){
		$em = $this->getDoctrine()->getManager();
		$aResult = array();
		$aLocal = $em->getRepository('BoAdminBundle:Local')->getAvailableByStatus();
		$em = $this->getDoctrine()->getManager();
		foreach($aLocal as $oLocal){
			if($this->isEmptyRoom($oLocal)==1){
				$aResult[]=$oLocal;
			}
		}
		return $aResult;
	}
	protected function updateEmptyLocal(){
		$aLocal = $this->getEmptyRoom();
		foreach($aLocal as $oLocal){
			$this->freeLocal($oLocal);
		}
		return true;
	}
	protected function updateUpcomingLocal(){
		$em = $this->getDoctrine()->getManager();
		$aLocal = $em->getRepository('BoAdminBundle:Local')->getUpcomingBug();
		foreach($aLocal as $oLocal){
			$this->updateStatusByLocal($oLocal);
		}
		return true;
	}
	//Update other room that have status empty but don't
	protected function updateOtherLocal(){
		$aLocal = $this->getRepository('BoAdminBundle:Local')->getFreeByStatus();
		foreach($aLocal as $oLocal){
			$iNumContract = $this->getNumberContractInRoom($oLocal);
			//if there is not no contract in the room then continue
			if($iNumContract==0) continue;
			$this->updateStatusByLocal($oLocal);
		}
		return true;
	}
	protected function updateAllLocal(){
		$this->updateEmptyLocal();
		$this->updateUpcomingLocal();
		$this->updateOtherLocal();
		return true;
	}
	protected function getWeekDays($month,$year){
		$aResult = array();
		for($i=1;$i<32;$i=$i+1){
			$day = date("N",mktime(0, 0, 0, $month, $i, $year));
			$oDate = new \DateTime($year."-".$month."-".$i);
			if($oDate->format("m")==$month){
				$aResult[$i]=($day==6 or $day==7)?0:1;
			}else{
				$aResult[$i]=0;
			}
		}
		return $aResult;		
	}
	protected function getTsDateAuthorized($oEmployee){
		//$em = $this->getDoctrine()->getManager();
		$bRes = 0;
		$param = $this->getParam("timesheet_date_override",17);
		if($param==1 or $oEmployee->getTsdate()==1) $bRes=1;
		return $bRes;
	}
	protected function getTsTime($oTs,$option){
		if($option=="am"){
			return $this->getRealTime($this->getDiffEndStart($oTs->getEndam(),$oTs->getStartam()));
		}else{
			return $this->getRealTime($this->getDiffEndStart($oTs->getEndpm(),$oTs->getStartpm()));
		}
		return null;
	}
	protected function getMonths(){
		$aResult = array();
		$aMonths = range(1,12);
		foreach($aMonths as $month){
			if(strlen($month)==1) $month="0".$month;
			$aResult[$month]=$month;
		}
		return $aResult;
	}
	protected function setTsAmOrPM($oTs){
		if($this->getTsTime($oTs,"am")>0 and $this->getTsTime($oTs,"pm")>0)  $oTs->setAmorpm("ALL");
		elseif($this->getTsTime($oTs,"am")>0) $oTs->setAmorpm("AM");
		elseif($this->getTsTime($oTs,"pm")>0) $oTs->setAmorpm("PM");
		return $oTs;		
	}
	protected function getTsForContractEmployee($oContract,$oEmployee){
		$em = $this->getDoctrine()->getManager();
		$aResult = array();
		$aTimesheet = $em->getRepository('BoAdminBundle:Timesheet')->getTsForContractEmployee($oContract,$oEmployee);
		foreach($aTimesheet as $oTs){
			$aResult[] = $oTs->getDdate()->format("Y-m-d");
		}
		return $aResult;
	}
	protected function getTsForGroupEmployee($oGroup,$oEmployee){
		$em = $this->getDoctrine()->getManager();
		$aResult = array();
		$aTimesheet = $em->getRepository('BoAdminBundle:Timesheet')->getTsForGroupEmployee($oGroup,$oEmployee);
		foreach($aTimesheet as $oTs){
			$aResult[] = $oTs->getDdate()->format("Y-m-d");
		}
		return $aResult;
	}
	protected function formatTsDate($aTsdate){
		$sRes = "";
		foreach($aTsdate as $ddate){
			$sRes = $sRes.$ddate.',';
		}
		$sRes = substr($sRes,0,-1);
		return $sRes;
	}
	//retourne les unités de charge en heure pour les cp suivant le champs
	protected function getChargeParamter(){
		$aResult = array();
		$em = $this->getDoctrine()->getManager();
		$aField = $em->getRepository('BoAdminBundle:Workfields')->findBy(array(),array('wfname' => 'asc'));
		foreach($aField as $oField){
			$aResult[$oField->getId()]=$oField->getChargescale();
		}
		return $aResult;
	}
	protected function isFullTime($oContract){
		if($oContract->getTypetime()=="Full Time") return true;
		return false;
	}
	protected function isPartTime($oContract){
		if($oContract->getTypetime()=="Part Time") return true;
		return false;
	}
	protected function getCpChargeByEmployee($oEmployee){
		$em = $this->getDoctrine()->getManager();
		$oRepContract = $em->getRepository('BoAdminBundle:Contracts');
		$aContracts = $oRepContract->findBy(array('advisor'=>$oEmployee,'status'=>1));
		return $this->getCpCharge($aContracts);
	}
	protected function updateAdvisorCharge($oEmployee){
		$res=0;
		$aCharge = $this->getCpChargeByEmployee($oEmployee);
		$aAdvisors = $this->getRepository('BoAdminBundle:Advisors')->findBy(array('advisor'=>$oEmployee));
		if(isset($aAdvisors[0]) and $oAdvisor=$aAdvisors[0]){
			if(isset($aCharge['hour'])){
				$oAdvisor->setHourlycharge($aCharge['hour']);
			}
			if(isset($aCharge['percent'])){
				$oAdvisor->setPercentcharge($aCharge['percent']);
			}
			$res = $this->updateEntity($oAdvisor);		
		}
		return $res; 
	}
	protected function updateAllAdvisorCharge(){
		$aAdvisors = $this->getRepository('BoAdminBundle:Advisors')->findAll();		
		foreach($aAdvisors as $oAdvisor){
			$oEmployee = $oAdvisor->getAdvisor();
			if($oEmployee){
				$res = $this->updateAdvisorCharge($oEmployee);
			}					
		}
		return;
	}
	public function getOverCharged(){
		$aResult = array();
		$aAdvisors = $this->getRepository('BoAdminBundle:Advisors')->findAll();	
		foreach($aAdvisors as $oAdvisor){
			$authorized = $oAdvisor->getAdminhour();
			$charge = $oAdvisor->getHourlycharge();
			if($charge>=$authorized) $aResult[] =  $oAdvisor;
		}
		return $aResult;
	}
	//Update charge for only cp whom charge exced 
	protected function checkAndupdateCpCharge(){
		$aAdvisors = $this->getOverCharged();
		foreach($aAdvisors as $oAdvisor){
			$oEmployee = $oAdvisor->getAdvisor();
			if($oEmployee){
				$res = $this->updateAdvisorCharge($oEmployee);
			}
		}					
		return;
	}
	protected function getCpCharge($aContracts){
		$iNf1=$iNf2=$iNf7=$iPft=$iNf8=$iNf9b=$other=0;
		$aNG1 = $aNG2 = array();

		foreach($aContracts as $contract){
			if($this->getWorkfields($contract)!=null and $this->getWorkfields($contract)=="Field 1"){
				if($contract->getGroup()==null) continue;
				if(isset($aNG1[$contract->getGroup()->getId()])) continue;
				$aNG1[$contract->getGroup()->getId()] = $contract->getGroup()->getName();
			}elseif($this->getWorkfields($contract)!=null and $this->getWorkfields($contract)=="Field 2"){
				if($contract->getGroup()==null) continue;
				if(isset($aNG2[$contract->getGroup()->getId()])) continue;
				$aNG2[$contract->getGroup()->getId()] = $contract->getGroup()->getName();				
			}elseif($this->getWorkfields($contract)!=null and $this->getWorkfields($contract)=="Field 7"){
				$iNf7=$iNf7+1;
			}elseif($this->getWorkfields($contract)!=null and $this->getWorkfields($contract)=="Field 8"){
				$iNf8=$iNf8+1;
			}elseif($this->isFullTime($contract)==true){
				$iPft=$iPft+1;
			}elseif($this->getWorkfields($contract)!=null and $this->getWorkfields($contract)=="Field 9B") $iNf9b=$iNf9b+1;
			else $other=$other+1;
		}
		$iNf1 = count($aNG1);
		$iNf2 = count($aNG2);
		$aWf = $this->getChargeParamter();
		if($aWf!=null){
			$charge = ($iNf1*$aWf[1])+($iNf2*$aWf[1])+($iNf7*$aWf[3])+($iNf8*$aWf[3])+($iPft*$aWf[3])+($iNf9b*$aWf[6])+($other*$aWf[6]);
			$iPercent = ($charge*100)/35;			
		}else{
			$charge = $iPercent = 0;
		}
		return array('hour'=>$charge,'percent'=>$iPercent,'field7'=>$iNf7,'field8'=>$iNf8,'pft'=>$iPft,'field1'=>$iNf1,'field2'=>$iNf2,'field9b'=>$iNf9b,'other'=>$other);		
	}
	protected function getDetailCharge($aCharge,$aParam){
		$aResult=array();
		if(isset($aCharge['field7']) and $aCharge['field7']!=0){
			$fTotal = $aCharge['field7']*$aParam[3];
			$aResult[] = array('label'=>'7','number'=>$aCharge['field7'],'param'=>$aParam[3],'total'=>$fTotal); 
		}
		if(isset($aCharge['field8']) and $aCharge['field8']!=0){
			$fTotal = $aCharge['field8']*$aParam[3];
			$aResult[] = array('label'=>'8','number'=>$aCharge['field8'],'param'=>$aParam[3],'total'=>$fTotal); 
		}
		if(isset($aCharge['field1']) and $aCharge['field1']!=0){
			$fTotal = $aCharge['field1']*$aParam[1];
			$aResult[] = array('label'=>'1','number'=>$aCharge['field1'],'param'=>$aParam[1],'total'=>$fTotal); 
		}
		if(isset($aCharge['field2']) and $aCharge['field2']!=0){
			$fTotal = $aCharge['field2']*$aParam[1];
			$aResult[] = array('label'=>'2','number'=>$aCharge['field2'],'param'=>$aParam[1],'total'=>$fTotal); 
		}
		if(isset($aCharge['pft']) and $aCharge['pft']!=0){
			$fTotal = $aCharge['pft']*$aParam[3];
			$aResult[] = array('label'=>'Priv. FT','number'=>$aCharge['pft'],'param'=>$aParam[3],'total'=>$fTotal); 
		}
		if(isset($aCharge['field9b']) and $aCharge['field9b']!=0){
			$fTotal = $aCharge['field9b']*$aParam[6];
			$aResult[] = array('label'=>'9B','number'=>$aCharge['field9b'],'param'=>$aParam[6],'total'=>$fTotal); 
		}
		if(isset($aCharge['other']) and $aCharge['other']!=0){
			$fTotal = $aCharge['other']*$aParam[6];
			$aResult[] = array('label'=>'Others','number'=>$aCharge['other'],'param'=>$aParam[6],'total'=>$fTotal); 
		}
		return $aResult;
	}
	protected function getAllOutCharged(){
		$aResult = array();
		$em = $this->getDoctrine()->getManager();
		$aAdvisors = $em->getRepository('BoAdminBundle:Advisors')->findAll();	
		foreach($aAdvisors as $oAdvisor){
			$oEmployee = $oAdvisor->getAdvisor();
			$adminhour = ($oAdvisor->getAdminhour()==0.00 or $oAdvisor->getAdminhour()==null)?35:$oAdvisor->getAdminhour();
			if($oEmployee){
				$this->updateAdvisorCharge($oEmployee);
				$bool = ($oAdvisor->getHourlycharge()>$adminhour)?1:0;
				$aResult[] = array($oEmployee->getId(),$bool);
			}					
		}
		return $aResult;
	}
	//Check if there exist a timesheet for the employee and the contract and the date given as parameters
	protected function existTsContract($oContract,$oEmployee,$oDate){
		$em = $this->getDoctrine()->getManager();
		$oRepTs = $em->getRepository('BoAdminBundle:Timesheet');
		return $oRepTs->existTsContract($oContract,$oEmployee,$oDate);
	}
	//Check if there exist a timesheet for the employee and the group and the date given as parameters
	protected function existTsGroup($oGroup,$oEmployee,$oDate){
		$em = $this->getDoctrine()->getManager();
		$oRepTs = $em->getRepository('BoAdminBundle:Timesheet');
		return $oRepTs->existTsGroup($oGroup,$oEmployee,$oDate);
	}
	protected function getAgendaTime(){
		$aResult = array();
		$aTime = array('07:00','07:15','07:30','07:45','08:00','08:15','08:30','08:45','09:00','09:15','09:30','09:45','10:00','10:15','10:30','10:45','11:00','11:15','11:30','11:45','12:00','12:15','12:30','12:45','13:00','13:15','13:30','13:45','14:00','14:15','14:30','14:45','15:00','15:15','15:30','15:45','16:00','16:15','16:30','16:45','17:00','17:15','17:30','17:45','18:00','18:15','18:30','18:45','19:00','19:15','19:30','19:45');
		foreach($aTime as $sTime){
			$aResult[$sTime] = $this->formatTime($sTime);
		}
		return $aResult; 
	}
	protected function getAmDaySubstitution($amhour,$employee,$oSubstitution,$oDate){
		$em = $this->getDoctrine()->getManager();	
		$sAdvisor=$room=$idgroup=$idcontract=$etsc=$etsg=null;
		$aResult = array();	
		if($oSubstitution->getIdcontract()>0){ 
			$oContract = $em->getRepository('BoAdminBundle:Contracts')->find($oSubstitution->getIdcontract());
			//get student name if the contract is not a group's contract else get group name
			if($oContract->getGroup()!=null){
				$student = $oContract->getGroup()->getName();
			}else{
				$student = $oSubstitution->getStudent();
				if($student==null) $student = $this->getStudentsBy($oContract);
			}
			//get local data : the campus and the room name
			$aLocal = $oContract->getLocal();
			if(isset($aLocal[0]) and $oLocal=$aLocal[0]) $room = $oLocal->getCampus().", local ".$oLocal->getReference(); 
			elseif($oContract->getAdresse()){
				$room = $oContract->getAdresse();
			}
			//get advisor fullname
			if($oContract->getAdvisor()) $sAdvisor=$oContract->getAdvisor()->getFirstname()." ".$oContract->getAdvisor()->getName();
			$idcontract=$oContract->getId();
			$etsc = $this->existTsContract($oContract,$employee,$oDate);
		}elseif($oSubstitution->getIdgroup()>0){
			$oGroup = $em->getRepository('BoAdminBundle:Group')->find($oSubstitution->getIdgroup());
			$student = "le groupe ".$oSubstitution->getStudent();
			if($student==null){
				if($oGroup and $oGroup->getName()) $student = $this->getLang()=="en"?"the group":"le groupe".$oGroup->getName();
			}
			//get local data : the campus and the room name
			$aLocal = $oGroup->getLocal();
			if(isset($aLocal[0]) and $oLocal=$aLocal[0]) $room = $oLocal->getCampus().", local ".$oLocal->getReference(); 
			//get advisor fullname
			if($oGroup->getAdvisor()) $sAdvisor=$oGroup->getAdvisor()->getFirstname()." ".$oGroup->getAdvisor()->getName();
			$idgroup=$oGroup->getId();	
			$etsg = $this->existTsGroup($oGroup,$employee,$oDate);
		}
		$sLibAm = $this->getAmEventDesc($oSubstitution,$employee,$student,$room,$sAdvisor,2,$oDate);
		return array('label'=>$sLibAm,'hour'=>$amhour,'option'=>2,'idcontract'=>$idcontract,'idgroup'=>$idgroup,'etsc'=>$etsc,'etsg'=>$etsg,'idsubs'=>$oSubstitution->getId(),'idagenda'=>0);
	}
	protected function getPmDaySubstitution($pmhour,$employee,$oSubstitution,$oDate){
		$em = $this->getDoctrine()->getManager();
		$sAdvisor=$room=$idgroup=$idcontract=$etsc=$etsg=null;
		$aResult = array();	
		if($oSubstitution->getIdcontract()>0){ 
			$oContract = $em->getRepository('BoAdminBundle:Contracts')->find($oSubstitution->getIdcontract());
			//get student name if the contract is not a group's contract else get group name
			if($oContract->getGroup()!=null){
				$student = $oContract->getGroup()->getName();
			}else{
				$student = $oSubstitution->getStudent();
				if($student==null) $student = $this->getStudentsBy($oContract);
			}
			//get local data : the campus and the room name
			$aLocal = $oContract->getLocal();
			if(isset($aLocal[0]) and $oLocal=$aLocal[0]) $room = $oLocal->getCampus().", local ".$oLocal->getReference(); 
			elseif($oContract->getAdresse()){
				$room = $oContract->getAdresse();
			}
			//get advisor fullname
			if($oContract->getAdvisor()) $sAdvisor=$oContract->getAdvisor()->getFirstname()." ".$oContract->getAdvisor()->getName();
			$idcontract=$oContract->getId();
			$etsc = $this->existTsContract($oContract,$employee,$oDate);
		}elseif($oSubstitution->getIdgroup()>0){
			$oGroup = $em->getRepository('BoAdminBundle:Group')->find($oSubstitution->getIdgroup());
			$student = $this->getLang()=="en"?"the group":"le groupe".$oSubstitution->getStudent();
			if($student==null){
				if($oGroup and $oGroup->getName()) $student = $this->getLang()=="en"?"the group":"le groupe".$oGroup->getName();
			}
			//get local data : the campus and the room name
			$aLocal = $oGroup->getLocal();
			if(isset($aLocal[0]) and $oLocal=$aLocal[0]) $room = $oLocal->getCampus().", local ".$oLocal->getReference(); 
			//get advisor fullname
			if($oGroup->getAdvisor()) $sAdvisor=$oGroup->getAdvisor()->getFirstname()." ".$oGroup->getAdvisor()->getName();
			//get the group id for the creation timesheet link on the agenda
			$idgroup=$oGroup->getId();	
			//Check if exist a timesheet for this date for the group			
			$etsg = $this->existTsGroup($oGroup,$employee,$oDate);			
		}
		$sLibPm = $this->getPmEventDesc($oSubstitution,$employee,$student,$room,$sAdvisor,2,$oDate);
		return array('label'=>$sLibPm,'hour'=>$pmhour,'option'=>2,'idcontract'=>$idcontract,'idgroup'=>$idgroup,'etsc'=>$etsc,'etsg'=>$etsg,'idsubs'=>$oSubstitution->getId(),'idagenda'=>0);
	}
	private function getRoom($oContract,$option=null){
		$room = "";
		//get local data : the campus and the room name
		if($oContract->getLocation()=="Web"){
			$room = $this->getTransBy().$oContract->getLocation();
		}elseif($oContract->getLocation()!="Campus" and $oContract->getAdresse()){
			$room = $this->getTransat().$oContract->getAdresse();
		}else{
			$oGroup = $oContract->getGroup(); 
			$aLocal = $option==null?$oContract->getLocal():$oGroup->getLocal();
			if(isset($aLocal[0]) and $oLocal=$aLocal[0]) $room = $oLocal->getCampus().", local ".$oLocal->getReference();
			if($room != "") $room = $this->getTransat().$room;
		}
		return $room;
	}

	
	protected function getAmDaySchedule($amhour,$employee,$oSchedule,$sStatusAbsence,$oDate,$sStudentAbsence,$sSubstitution=null){
		//get contract scheduled
		$oContract = $oSchedule->getContracts();
		//get contract scheduled
		$oGroup = $oSchedule->getGroup();
		$sAdvisor=$room=$idgroup=$idcontract=$etsc=$etsg=$student=null;
		$aResult = array();
		if($oGroup!=null){
			$student = $this->getLang()=="en"?"the group ":"le groupe ";
			$student = $student.$oGroup->getName();
			//get local data : the campus and the room name
			$room = $this->getRoom($oContract,1);
			//get advisor fullname
			if($oGroup->getAdvisor()) $sAdvisor=$oGroup->getAdvisor()->getFirstname()." ".$oGroup->getAdvisor()->getName();
			//get the group id for the creation timesheet link on the agenda
			$idgroup=$oGroup->getId();
			//Check if exist a timesheet for this date for the group
			$etsg = $this->existTsGroup($oGroup,$employee,$oDate);
		}elseif($oContract!=null){ 
			//get student name if the contract is not a group's contract else get group name
			if($oContract->getGroup()!=null){
				$student = $oContract->getGroup()->getName();
			}else{
				$student = $this->getStudentsBy($oContract);
			}
			//get local data : the campus and the room name
			$room = $this->getRoom($oContract);
			
			//get advisor fullname
			if($oContract->getAdvisor()) $sAdvisor=$oContract->getAdvisor()->getFirstname()." ".$oContract->getAdvisor()->getName();
			$idcontract=$oContract->getId();	
			//Check if exist a timesheet for this date for contracts
			$etsc = $this->existTsContract($oContract,$employee,$oDate);
						
		}
		$sLibAm = $oSchedule->getDescription()!=null?$this->getAmScheduledesc($oSchedule,$oDate):$this->getAmEventDesc($oSchedule,$employee,$student,$room,$sAdvisor,1,$oDate,$sStatusAbsence,$sStudentAbsence,$sSubstitution);
		$aReturn = array('label'=>$sLibAm,'hour'=>$amhour,'option'=>1,'idcontract'=>$idcontract,'idgroup'=>$idgroup,'etsc'=>$etsc,'etsg'=>$etsg,'idagenda'=>$oSchedule->getId(),'avail'=>$oSchedule->getAvailability());
		return $this->addAbsencesTo($aReturn,$sStatusAbsence,$sStudentAbsence,$oDate,$oSchedule,$sSubstitution,1);
	}
	protected function getPmDaySchedule($pmhour,$employee,$oSchedule,$sStatusAbsence,$oDate,$sStudentAbsence,$sSubstitution=null){
		//get contract scheduled
		$oContract = $oSchedule->getContracts();
		//get contract scheduled
		$oGroup = $oSchedule->getGroup();
		$sAdvisor=$room=$idgroup=$idcontract=$etsc=$etsg=$student=null;
		$aResult = array();
		if($oGroup!=null){
			$student = $this->getLang()=="en"?"the group ":"le groupe ";
			$student = $student.$oGroup->getName();
			//get local data : the campus and the room name
			$room = $this->getRoom($oContract,1);
			//get advisor fullname
			if($oGroup->getAdvisor()) $sAdvisor=$oGroup->getAdvisor()->getFirstname()." ".$oGroup->getAdvisor()->getName();	
			//get the group id for the creation timesheet link on the agenda			
			$idgroup=$oGroup->getId();
			//Check if exist a timesheet for this date, receive 1 if it exist, 0 else
			$etsg = $this->existTsGroup($oGroup,$employee,$oDate);
		}elseif($oContract!=null){ 
			//get student name if the contract is not a group's contract else get group name
			if($oContract->getGroup()!=null){
				$student = $oContract->getGroup()->getName();
			}else{
				$student = $this->getStudentsBy($oContract);
			}
			//get local data : the campus and the room name
			$room = $this->getRoom($oContract);
			//get advisor fullname
			if($oContract->getAdvisor()) $sAdvisor=$oContract->getAdvisor()->getFirstname()." ".$oContract->getAdvisor()->getName();
			//get the contract id for the creation timesheet link on the agenda	
			$idcontract=$oContract->getId();
			//Check if exist a timesheet for this date for contracts
			$etsc = $this->existTsContract($oContract,$employee,$oDate);
		}
		$sLibPm = $oSchedule->getDescription()!=null?$this->getPmScheduledesc($oSchedule,$oDate):$this->getPmEventDesc($oSchedule,$employee,$student,$room,$sAdvisor,1,$oDate,$sStatusAbsence,$sStudentAbsence,$sSubstitution);	
		$aReturn = array('label'=>$sLibPm,'hour'=>$pmhour,'option'=>1,'idcontract'=>$idcontract,'idgroup'=>$idgroup,'etsc'=>$etsc,'etsg'=>$etsg,'idagenda'=>$oSchedule->getId(),'avail'=>$oSchedule->getAvailability());
		//return $aReturn;
		return $this->addAbsencesTo($aReturn,$sStatusAbsence,$sStudentAbsence,$oDate,$oSchedule,$sSubstitution,2);
	}
	//get schedule description when it exists
	private function getAmScheduledesc($oSchedule,$oDate){
		$sEventLib = $oSchedule->getStartam()->format("H:i")." - ".$oSchedule->getEndam()->format("H:i")."<br>".$oSchedule->getDescription();
		if($this->isHolidaysBy($oDate,$oSchedule,1)==true) $sEventLib = $sEventLib."<br><br>".$this->getHoliday($oDate,1);
		$sStatusAbsence = $this->getAbsenceForSchedule($oSchedule,$oDate,1);
		if($sStatusAbsence!=null) $sEventLib = $sEventLib."<br><br>".$sStatusAbsence;
		return $sEventLib;
	}
	protected function getdayNumberHourBy($oContract){
		if($oContract->getHourperweek()==null) return null;
		if($oContract->getHourperweek()==35){
			return 7;
		}
		if($oContract->getNumberday()>0 and floatval($oContract->getHourperweek())>0){
			return floatval($oContract->getHourperweek())/floatval($oContract->getNumberday());
		}
		return null;
	}
	/*
	*Function to do calculation of Remaining Number day on a contract
	*@Param: $oContract object,$iRH float
	*@returm : Remaining Day : $iHD interger;
	*/
	protected function getRemainingDay($oContract,$iRH){
		$iHourPerday = $this->getdayNumberHourBy($oContract);
		if($iHourPerday==null) return null;
		$iRD = floatval($iRH)/floatval($iHourPerday);
		return $iRD;
	}
	/*
	*Function to do calculation of estimated date of contract end
	*@Param: $oContract object,$iRH float
	*@returm : Estimated date : $oDate Date;
	*/
	protected function getEstimatedDate($oSchedule,$iRH,$oDate=null){
		$dDate=null;
		if($oDate==null) $oDate = $this->getToday();
		$oContract = $oSchedule->getContracts();
		if($oContract==null) return $oContract;
		$iRD = $this->getRemainingDay($oContract,$iRH);
		if(is_float($iRD)) $iRD = number_format($iRD,2);
		//$dDate = $this->getDatePlusthree($oSchedule,$oDate,$iRD);
		return array($iRD,$dDate);
	}
	protected function getEstimatedDateTwo($oContract,$iRH,$oDate=null){
		$dDate=null;
		if($oDate==null) $oDate = $this->getToday();
		if($oContract==null) return $oContract;
		$iRD = $this->getRemainingDay($oContract,$iRH);
		//$dDate = $this->getDatePlusFour($oContract,$oDate,$iRD);
		return array($iRD,$dDate);
	}
	//get schedule description when it exists
	private function getPmScheduledesc($oSchedule,$oDate){
		$sEventLib = $oSchedule->getStartpm()->format("H:i")." - ".$oSchedule->getEndpm()->format("H:i")."<br>".$oSchedule->getDescription();
		if($this->isHolidaysBy($oDate,$oSchedule,2)==true) $sEventLib = $sEventLib."<br><br>".$this->getHoliday($oDate,2);
		$sStatusAbsence = $this->getAbsenceForSchedule($oSchedule,$oDate,2);
		if($sStatusAbsence!=null) $sEventLib = $sEventLib."<br><br>".$sStatusAbsence;
		return $sEventLib;
	}
	//add absences holidays, teacher absence or leaner absence to content
	private function addAbsencesTo($aReturn,$sStatusAbsence,$sStudentAbsence,$oDate,$oSchedule,$sSubstitution,$option){
		if($this->isHolidaysBy($oDate,$oSchedule,$option)==true){
			$aReturn['absence'] = 1;
		}else{
			if($sStatusAbsence!=null) $aReturn['absence'] = 1;
			if($sStudentAbsence!=null or $sSubstitution!=null) $aReturn['s_absence'] = 1;
		}
		return $aReturn;
	}
	private function getAmHour($oEntity){
		if($oEntity==null) return $oEntity;
		return $this->getRealAmHour($oEntity);
	}
	private function getPmHour($oEntity){
		if($oEntity==null) return $oEntity;
		return $this->getRealPmHour($oEntity);
	}
	private function getAmHourWithBreak($oEntity){
		if($oEntity==null) return $oEntity;
		$hour = $this->getRealAmHour($oEntity);
		if($hour>=3) $hour=$hour-0.25;
		return $hour; 
		//La pause a ajouter
	}
	private function getPmHourWithBreak($oEntity){
		if($oEntity==null) return $oEntity;
		$hour = $this->getRealPmHour($oEntity);
		if($hour>=3) $hour=$hour-0.25;
		return $hour; 
		//La pause a ajouter
	}
	protected function getHourWithBreak($oEntity,$option){
		if($oEntity==null) return $oEntity;
		if($option == self::OPTION_DAY_ONE){
			$rhour = $this->getRealAmHour($oEntity);
			$hour = ($oEntity->getBam()==false or $oEntity->getBam()==0)?$rhour:floatval($rhour)-0.25; 
		}else{
			$rhour = $this->getRealPmHour($oEntity);
			$hour = ($oEntity->getBpm()==false or $oEntity->getBpm()==0)?$rhour:floatval($rhour)-0.25; 
		}
		return $hour; 
	}
	protected function getRealAmHour($oEntity){
		if($oEntity==null) return $oEntity;
		return floatval($this->getDiffTime($oEntity->getStartam(),$oEntity->getEndam()));
	}
	protected function getRealPmHour($oEntity){
		if($oEntity==null) return $oEntity;
		return ($this->getDiffTime($oEntity->getStartpm(),$oEntity->getEndpm()));
	}
	//Get and return the sum of am hour and pm hour for training or schedule
	protected function getAllHour($oEntity){
		return $this->getRealAmHour($oEntity)+$this->getRealPmHour($oEntity);
	}
	//get hour for schedule when the length of schedule's array is equal 2
	protected function getFor2Schedules($aSchedule){
		return	($this->getAllHour($aSchedule[0])+$this->getAllHour($aSchedule[1]));	
	}
	//get real hour by option for substitution and schedule
	protected function getRealHourByOption($oEntity,$option){
		if($option == self::OPTION_DAY_ONE) return $this->getRealAmHour($oEntity);
		if($option == self::OPTION_DAY_TWO) return $this->getRealPmHour($oEntity);
		return 0;
	}
	//get real hour by option for schedule
	protected function getRealHourScheduled($oSchedule,$option){
		$hour = $this->getRealHourByOption($oSchedule,$option);
		$breakValue = $this->getBreakValue();
		if($this->isBreakByOption($oSchedule,$option)==true) return floatval($hour)-floatval($breakValue);
		return $hour;
	}
	//get hour scheduled for a day is equal of the hour of AM and hour of PM
	protected function getRealHourPerDay($oSchedule){
		return floatval($this->getRealHourScheduled($oSchedule,1))+floatval($this->getRealHourScheduled($oSchedule,2));
	}
	protected function getScheduleByDate($employee,$oDate){
		$aResult = array();
		$oRepTsc = $this->getRepository('BoAdminBundle:Agenda');	
		$aSchedule = $oRepTsc->getScheduleByDate($employee->getId(),$oDate);	
		foreach($aSchedule as $oSchedule){		
			$aResult[] = $oSchedule;		
		}
		return $aResult;
	}
	protected function getActiveScheduleByDate($oEmployee,$oDate){
		$oRepTsc = $this->getRepository('BoAdminBundle:Agenda');	
		$aSchedule = $oRepTsc->getActiveByEmployeeAndDate($oEmployee,$oDate);
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($this->existDaySchedule($oSchedule,$oDate)==1) $aResult[] = $oSchedule;
		}
		return $aResult;
	}
    	/**
     	* get active scheduled by employee and date
     	*
     	* @param Employee $oEmployee and date $oDate
     	*
     	* @return array of schedule
     	*/
	protected function getScheduleByDateAndStatus($oEmployee,$oDate){
		$oRepTsc = $this->getRepository('BoAdminBundle:Agenda');	
		$aSchedule = $oRepTsc->getActiveByDateAndStatus($oEmployee,$oDate);
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($this->existDaySchedule($oSchedule,$oDate)==1){
                            $aResult[] = $oSchedule;
                        }
		}
		return $aResult;
	}
	protected function getScheduleByKey($aSchedule,$oDate,$option){
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($option == self::OPTION_DAY_ONE and $oSchedule->getStartam()){
                            $key = $this->getRealTime($oSchedule->getStartam()); 
                        }
			if(($option == self::OPTION_DAY_TWO or $option == self::OPTION_DAY_THREE) and $oSchedule->getStartpm()){
                            $key = $this->getRealTime($oSchedule->getStartpm());
                        }
			if($this->existDaySchedule($oSchedule,$oDate)==1 and isset($key)){
				if(isset($aResult[$key])){
                                    $key = $key*2;
                                }
				$aResult[$key] = $oSchedule;
			} 
		}
		return $aResult;
	}
	private function getScheduleByPriority($aSchedule){
		$oWait=null;
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if(count($oSchedule->getAbsences())>0){
				$oWait= $oSchedule;
				continue;
			}
			if($oSchedule->getPriority()==1) $priority = 1;
			$aResult[] = $oSchedule;
		}
		if(!isset($priority) and $oWait!=null) $aResult[] = $oWait;
		return $aResult;
	}
	//find the lastest schedule entered
	private function getByPriority($aSchedule){
        	$oldday = new \DateTime("2000-01-01");
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getStartam()>$oldday){
				$token = $oSchedule;
				$oldday = $oSchedule->getStartam();
			}
		}
		return $token;
	}
	private function getByHolderAndDate($idemployee,$oDate){
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		return $oRepSubs->getByHolderAndDate($idemployee,$oDate);
	}
	private function getBySubstituteAndDate($idemployee,$oDate){
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		return $oRepSubs->getBySubstituteAndDate($idemployee,$oDate);
	}
	private function getByDateAndTime($idemployee,$oDate,$option){
		$aSubstitution = $this->getByHolderAndDate($idemployee,$oDate);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE){
                            return $oSubstitution;
                        }
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO){
                            return $oSubstitution;	
                        }
		}
		return null;
	}
	protected function getByDateAndTimeBis($idemployee,$oDate,$option){
		$aResult = array();
		$aSubstitution = $this->getBySubstituteAndDate($idemployee,$oDate);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE){
                            $aResult[] = $oSubstitution;
                        }
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO){
                            $aResult[] = $oSubstitution;	
                        }
		}
		return $aResult;
	}
	protected function getSubsByStudent($oStudent,$oDate,$option,$oContract=null){
		$aResult = array();
		if($oContract == null){
                    $oContract = $this->getStudentContractByDate($oStudent,$oDate);
                }
		if($oContract != null){
                    return $this->getSubsByContract($oContract,$oDate,$option);
                }
		return $aResult;
	}	
	protected function getSubsByContract($oContract,$oDate,$option){
		$aResult = array();
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		$aSubstitution = $oRepSubs->getByContract($oContract,$oDate);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE){
                            $aResult[] = $oSubstitution;
                        }
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO){
                            $aResult[] = $oSubstitution;	
                        }
		}
		return $aResult;
	}
	protected function getSubsByGroup($oGroup,$oDate,$option){
		$aResult = array();
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		$aSubstitution = $oRepSubs->getByGroup($oGroup,$oDate);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE){
                            $aResult[] = $oSubstitution;
                        }
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO){
                            $aResult[] = $oSubstitution;	
                        }
		}
		return $aResult;
	}
	protected function generateAgenda($employee,$aTimes,$aDates){
		$em = $this->getDoctrine()->getManager();
		$aAgenda = array();
		foreach($aDates as $key=>$oDate){
			$aSubstitution = $this->getByHolderAndDate($employee->getId(),$oDate);
                        
			$aSchedule = $this->getScheduleByDate($employee,$oDate);
			$aAgenda   = $this->getAmScheduled($employee,$oDate,$aSubstitution,$aSchedule,$key,$aAgenda);
			$aAgenda   = $this->getPmScheduled($employee,$oDate,$aSubstitution,$aSchedule,$key,$aAgenda);
		}
		return $aAgenda;
	}
	private function getAmSubsAgenda($aAgenda,$aSubstitution,$employee,$oDate,$key){
		$oSubstitution = $this->existSubs($aSubstitution,1,$employee->getId());
		$aAgenda[$key][$oSubstitution->getStartam()->format("H:i")] = $this->getAmDaySubstitution($this->getAmHour($oSubstitution),$employee,$oSubstitution,$oDate);
		$aAgenda[$key][$oSubstitution->getStartam()->format("H:i")]['startam'] = $this->formatTime($oSubstitution->getStartam()->format("H:i"));
		$aAgenda[$key][$oSubstitution->getStartam()->format("H:i")]['endam'] = $this->formatTime($oSubstitution->getEndam()->format("H:i"));
		return $aAgenda;
	}
	protected function getAmScheduled($employee,$oDate,$aSubstitution,$aSchedule,$key,$aAgenda){
		if(count($aSchedule)==0 and count($aSubstitution)>0 and $this->existSubs($aSubstitution,1,$employee->getId()) and $this->isWeekend($oDate)==false){
			$aAgenda=$this->getAmSubsAgenda($aAgenda,$aSubstitution,$employee,$oDate,$key);								
		}elseif(count($aSchedule)>0){
			$aSchedule = $this->getScheduleByPriority($aSchedule);
			foreach($aSchedule as $oSchedule){
				$amhour = $this->getAmHour($oSchedule);	

				if(isset($aSubstitution[0]) and $this->existSubs($aSubstitution,1,$employee->getId()) and $this->isWeekend($oDate)==false){
					$aAgenda=$this->getAmSubsAgenda($aAgenda,$aSubstitution,$employee,$oDate,$key);					
				}elseif($amhour>0 and $this->existDaySchedule($oSchedule,$oDate)==1){
					$overlap = $this->checkAmOverlap($aAgenda,$oSchedule,$key);	
					if($oSchedule->getStatus()==1 or $oSchedule->getStatus()==2){
						if($overlap==false){
							$aAgenda=$this->getAmSchAgenda($aAgenda,$employee,$oSchedule,$oDate,$key,$amhour);					}
					}elseif($oDate<$this->getToday()){
						$aAgenda = $this->getPmSchAgenda($aAgenda,$employee,$oSchedule,$oDate,$key,$amhour);
					}
				}
			}			
		}
//exit(0);
		return $aAgenda;
	}
	private function getAmSchAgenda($aAgenda,$employee,$oSchedule,$oDate,$key,$amhour){
		$sStatusAbsence = $this->getAbsenceForSchedule($oSchedule,$oDate,1);
		if($oSchedule->getContracts()) $sStudentAbsence = $this->getAbsenceForStudent($oSchedule->getContracts(),$oDate,1);
		$aAgenda[$key][$oSchedule->getStartam()->format("H:i")] = $this->getAmDaySchedule($amhour,$employee,$oSchedule,$sStatusAbsence,$oDate,$sStudentAbsence);
		$aAgenda[$key][$oSchedule->getStartam()->format("H:i")]['startam'] = $this->formatTime($oSchedule->getStartam()->format("H:i"));
		$aAgenda[$key][$oSchedule->getStartam()->format("H:i")]['endam'] = $this->formatTime($oSchedule->getEndam()->format("H:i"));		
		return $aAgenda;
	}
	private function getPmSchAgenda($aAgenda,$employee,$oSchedule,$oDate,$key,$pmhour){
		//Check if there exists a absence for this day; option=1 then for AM and option=2 then for PM
		$sStatusAbsence = $this->getAbsenceForSchedule($oSchedule,$oDate,2);
		if($oSchedule->getContracts()) $sStudentAbsence = $this->getAbsenceForStudent($oSchedule->getContracts(),$oDate,2);
		$aAgenda[$key][$oSchedule->getStartpm()->format("H:i")] = $this->getPmDaySchedule($pmhour,$employee,$oSchedule,$sStatusAbsence,$oDate,$sStudentAbsence);
		$aAgenda[$key][$oSchedule->getStartpm()->format("H:i")]['startpm'] = $this->formatTime($oSchedule->getStartpm()->format("H:i"));
		$aAgenda[$key][$oSchedule->getStartpm()->format("H:i")]['endpm'] = $this->formatTime($oSchedule->getEndpm()->format("H:i"));	
		return $aAgenda;
	}
	private function checkAmOverlap($aAgenda,$oSchedule,$key){
		if(!isset($aAgenda[$key])) return false;
		if(!isset($aAgenda[$key]['startam']) or !isset($aAgenda[$key]['endam'])) return false;
		$endam = $oSchedule->getEndam()==null?$this->getTime():$oSchedule->getEndam();
		$startam = $oSchedule->getStartam()==null?$this->getTime():$oSchedule->getStartam();
		if($this->formatTime($endam->format("H:i"))<=$aAgenda[$key]['startam'] or $this->formatTime($startam->format("H:i"))>=$aAgenda[$key]['endam']) return false;
		if($oSchedule->getPriority()==1) return false;		
		return true;
	}
	private function getPmSubsAgenda($aAgenda,$aSubstitution,$employee,$oDate,$key){
		$oSubstitution = $this->existSubs($aSubstitution,2,$employee->getId());
		$aAgenda[$key][$oSubstitution->getStartpm()->format("H:i")] = $this->getPmDaySubstitution($this->getPmHour($oSubstitution),$employee,$oSubstitution,$oDate);
		$aAgenda[$key][$oSubstitution->getStartpm()->format("H:i")]['startpm'] = $this->formatTime($oSubstitution->getStartpm()->format("H:i"));
		$aAgenda[$key][$oSubstitution->getStartpm()->format("H:i")]['endpm'] = $this->formatTime($oSubstitution->getEndpm()->format("H:i"));	
		return $aAgenda;
	}
	protected function getPmScheduled($employee,$oDate,$aSubstitution,$aSchedule,$key,$aAgenda){
		if(count($aSchedule)==0 and count($aSubstitution)>0 and $this->existSubs($aSubstitution,2,$employee->getId()) and $this->isWeekend($oDate)==false){
			$aAgenda = $this->getPmSubsAgenda($aAgenda,$aSubstitution,$employee,$oDate,$key);							
		}elseif(count($aSchedule)>0){
			$aSchedule = $this->getScheduleByPriority($aSchedule);
			foreach($aSchedule as $oSchedule){
				$pmhour = $this->getPmHour($oSchedule);
				if(isset($aSubstitution[0]) and $this->existSubs($aSubstitution,2,$employee->getId()) and $this->isWeekend($oDate)==false){
					$aAgenda = $this->getPmSubsAgenda($aAgenda,$aSubstitution,$employee,$oDate,$key);			
				}elseif($pmhour>0 and $this->existDaySchedule($oSchedule,$oDate)==1){
					//vérifier s'il y a chevauchement en AM, retourne false s'il n'y a pas chevauchement ou true sinon
					$overlap = $this->checkPmOverlap($aAgenda,$oSchedule,$key);
					if($oSchedule->getStatus()==1 or $oSchedule->getStatus()==2){
						if($overlap==false){
							$aAgenda = $this->getPmSchAgenda($aAgenda,$employee,$oSchedule,$oDate,$key,$pmhour);
						}
					}elseif($oDate<$this->getToday()){
						$aAgenda = $this->getPmSchAgenda($aAgenda,$employee,$oSchedule,$oDate,$key,$pmhour);
					}
				}
			}
		}		
		return $aAgenda;
	}
	private function checkPmOverlap($aAgenda,$oSchedule,$key){
		if(!isset($aAgenda[$key])) return false;
		if(!isset($aAgenda[$key]['startpm']) or !isset($aAgenda[$key]['endpm'])) return false;
		$endpm = $oSchedule->getEndpm()==null?$this->getTime():$oSchedule->getEndpm();
		$startpm = $oSchedule->getStartpm()==null?$this->getTime():$oSchedule->getStartpm();
		if($this->formatTime($endpm->format("H:i"))<$aAgenda[$key]['startpm'] or $this->formatTime($startpm->format("H:i"))>$aAgenda[$key]['endpm']) return false;
		if($oSchedule->getPriority()==1) return false;
		return true;
	}
	//Employee's Absence grouped 
	private function getEmployeeAbsenceByDate($employee,$oDate){
		$oRepAbs = $this->getRepository('BoAdminBundle:AbsEmp');
		return $oRepAbs->getEmployeeAbsencesByDate($employee,$oDate);
	}
	public function isAbsentEmployee($employee,$oDate,$option){
		$oRepAbs = $this->getRepository('BoAdminBundle:AbsEmp');
		$aAbsence = $oRepAbs->getEmployeeAbsencesByDate($employee,$oDate);
		if(count($aAbsence)==0) return false;
		$oAbsence=$aAbsence[0];
		if($option == self::OPTION_DAY_THREE and $oAbsence->getAmorPm()== PERIOD_DAY_ALL) return true;
		if($oAbsence->getAmorPm()== self::PERIOD_DAY_ALL && ($option == self::OPTION_DAY_ONE or $option == self::OPTION_DAY_TWO)) return true;
		if($oAbsence->getAmorPm() == self::PERIOD_DAY_AM && $option == self::OPTION_DAY_ONE) return true;
		if($oAbsence->getAmorPm()== self::PERIOD_DAY_PM && $option == self::OPTION_DAY_TWO) return true;
		return false;
	}
	protected function getEmployeeTodayAbsences($employee){
		$today = new \DateTime(date("d-m-Y"));
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:AbsEmp');
		return  $oRepAbs->getEmployeeAbsencesByDate($employee,$today);		
	}
	protected function getContratAbsences($employee,$aTSC){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:AbsEmp');
		$aAbsence = $oRepAbs->getEmployeeAbsencesByDate($employee,$today);		
		foreach($aTSC as $oTSC){
			if($oTSC->getContracts() and count($aAbsence)==1){
				if($aAbsence[0]->getAmorpm()== self::PERIOD_DAY_ALL or ($aAbsence[0]->getAmorpm() == self::PERIOD_DAY_AM && $this->getRealAmHour($oTSC)>0) || ($aAbsence[0]->getAmorpm()== self::PERIOD_DAY_PM && $this->getRealPmHour($oTSC)>0)) $aResult[$oTSC->getContracts()->getId()]=1;

			}
		}
		return $aResult;
	}
	protected function getGroupAbsences($employee,$aTSC){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:AbsEmp');
		$aAbsence = $oRepAbs->getEmployeeAbsencesByDate($employee,$today);	
		foreach($aTSC as $oTSC){
			if($oTSC->getGroup() and count($aAbsence)==1){
				if(
                                        $aAbsence[0]->getAmorpm()== PERIOD_DAY_ALL || 
                                        ($aAbsence[0]->getAmorpm() == self::PERIOD_DAY_AM && $this->getRealAmHour($oTSC)>0) ||
                                        ($aAbsence[0]->getAmorpm()== self::PERIOD_DAY_PM && $this->getRealPmHour($oTSC)>0)
                                ){
                                    $aResult[$oTSC->getGroup()->getId()]=1;                                  
                                }
			}			
		}
		return $aResult;
	}
	protected function getEmployeeAbsByDate($oEmployee,$oDate=null){
		if($oDate==null) $oDate = $this->getToday();
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:AbsEmp');
		$aAbsences = $oRepAbs->getEmployeeAbsencesByDate($oEmployee,$oDate);
		return $aAbsences; 
	}
	public function isAbsentStudent($oStudent,$oDate,$option){
		$oRepAbs  = $this->getRepository('BoAdminBundle:Absences');
		$aAbsence = $oRepAbs->getStudentAbsencesByDate($oStudent,$oDate);
		if(count($aAbsence)==0){
                    return false;
                }
		$oAbsence=$aAbsence[0];
		if($option == self::OPTION_DAY_THREE and $oAbsence->getAmorPm()== PERIOD_DAY_ALL) return true;
		if($oAbsence->getAmorPm() == self::PERIOD_DAY_ALL && ($option == self::OPTION_DAY_ONE or $option == self::OPTION_DAY_TWO)){ 
                    return true;
                }
		if($oAbsence->getAmorPm() == self::PERIOD_DAY_AM && $option == self::OPTION_DAY_ONE){ 
                    return true;
                }
		if($oAbsence->getAmorPm() == self::PERIOD_DAY_PM && $option == self::OPTION_DAY_TWO){ 
                    return true;
                }
		return false;
	}
	protected function isAbsentStudent2($oSchedule,$oDate,$option){
		if($this->isNoshow($oSchedule,$oDate,$option)==true) return false;
		$aStudent = $this->getStudentByAgenda($oSchedule);
		if(count($aStudent)!=1) return "error";
		$oStudent = $aStudent[0];
		return $this->isAbsentStudent($oStudent,$oDate,$option);
	} 
	//This function verify if there exists absence of the contract's holder for this day schedule
	//option=1 then for AM and option=2 then for PM
	private function getAbsenceForSchedule($oSchedule,$oDate,$option){
		$employee = $oSchedule->getEmployee();
		$aAbsence = $this->getEmployeeAbsenceByDate($employee,$oDate);
		if(count($aAbsence)>0){
                    return $this->getEmployeeAbsenceStatus($employee,$aAbsence,$option);
                }
		return $this->getScheduleCancel($oSchedule,$oDate,$option);
	}
	private function getEmployeeAbsenceStatus($employee,$aAbsence,$option){
		$status = null;
		if(isset($aAbsence[0])){
			if(($aAbsence[0]->getAmorpm() == self::PERIOD_DAY_AM or $aAbsence[0]->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_ONE){ 
                            $sLabel = $this->getLang()=="en"?" absent this morning":" absent ce matin";
                        }    
			if(($aAbsence[0]->getAmorpm()== self::PERIOD_DAY_PM or $aAbsence[0]->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_TWO){
                            $sLabel = $this->getLang()=="en"?" absent this afternoon":" absent cet après-midi";
                        }
			if(isset($sLabel)){ 
                            $status = $employee->getFirstname()." ".$employee->getName().$sLabel;
                        }
		}
		return $status;
	}
	private function getScheduleCancel($oSchedule,$oDate,$option){
		$oRepCan = $this->getRepository('BoAdminBundle:Cancel');
		$aCancel = $oRepCan->getByAgendaAndDate($oSchedule,$oDate);		
		$status = null;
		if(isset($aCancel[0])){
			if(($aCancel[0]->getAmorpm() == self::PERIOD_DAY_AM || $aCancel[0]->getAmorpm()== self::PERIOD_DAY_ALL) && $option == self::OPTION_DAY_ONE){
                            $sLabel = $this->getLang()=="en"?" Cancelled this morning":" Annule ce matin";
                        }
			if(($aCancel[0]->getAmorpm() == self::PERIOD_DAY_PM || $aCancel[0]->getAmorpm()== self::PERIOD_DAY_ALL) && $option == self::OPTION_DAY_TWO){
                            $sLabel = $this->getLang()=="en"?" Cancelled this afternoon":" Annule cet après-midi";
                        }
			if(isset($sLabel)){
                            $status = $sLabel.": ".$aCancel[0]->getMotif();
                        }
		}
		return $status;
	}
	//Fuction return 1 when the teacher is absent, 0 else
	private function isEmployeeAbsent($employee,$oDate,$option){
		$aAbsence = $this->getEmployeeAbsenceByDate($employee,$oDate);
		if(isset($aAbsence[0])){
			if($aAbsence[0]->getAmorpm()== self::PERIOD_DAY_ALL or ($aAbsence[0]->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE) or ($aAbsence[0]->getAmorpm()== self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO)) return 1;
		}
		return 0;
	}
	//This function verify if there exists absence of the student
	//option=1 then for AM and option=2 then for PM
	private function getAbsenceForStudent($oContract,$oDate,$option){
		$sLabel = null;
		$oGroup = $oContract->getGroup();
		if($oGroup!=null){
			$aAbsence = $this->getGroupAbsByDate($oGroup,$oDate);
			if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
				$groupname = "All students of group ".$oGroup->getName();
				if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_ONE){
					 $sLabel = $this->getLang()=="en"?$groupname." absent this morning":$groupname." absent ce matin";
					if($this->getNoShow($oAbsence,$oDate,$option)==1){
                                            $sLabel = $sLabel." (No-show)";
                                        }
					else{
                                            $sLabel = $sLabel.$this->getTransCancel();
                                        }
				}
				if(($oAbsence->getAmorpm()== self::PERIOD_DAY_PM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_TWO){
					$sLabel = $sLabel = $this->getLang()=="en"?$groupname." absent this afternoon":$groupname." absent cet après-midi";
					if($this->getNoShow($oAbsence,$oDate,$option)==1)  $sLabel = $sLabel." (No-show)";
					else $sLabel = $sLabel.$this->getTransCancel();
				}
			}else return $sLabel;
		}
		//if there are many students on the contract then not to report this absence and the status is null
		$oStudent = $this->getArrayZero($oContract->getStudents());
		if($oStudent==null) return $sLabel;
		$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			$student = $this->getFullNameOf($oStudent);
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_ONE){
				 $sLabel = $this->getLang()=="en"?$student." absent this morning":$student." absent ce matin";
				if($this->getNoShow($oAbsence,$oDate,$option)==1)  $sLabel = $sLabel." (No-show)";
				else $sLabel = $sLabel.$this->getTransCancel();
			}
			if(($oAbsence->getAmorpm()== self::PERIOD_DAY_PM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_TWO){
				$sLabel = $this->getLang()=="en"?$student." absent this afternoon":$student." absent cet après-midi";
				if($this->getNoShow($oAbsence,$oDate,$option)==1){
                                    $sLabel = $sLabel." (No-show)";
                                }
				else{
                                    $sLabel = $sLabel.$this->getTransCancel();
                                }
			}
		}		
		return $sLabel;
	}
	private function getNoShow($oAbsence,$oDate,$option){
		$i=1;
		$startdate = $oAbsence->getStartdate();
		$nj = intval($this->getDiffDate($oDate,$startdate));
		if($nj<$oAbsence->getDayns()){
			if($oAbsence->getNsam()==true and $option == self::OPTION_DAY_ONE) return 1;
			elseif($oAbsence->getNspm()==true and $option == self::OPTION_DAY_TWO) return 1;
			elseif($oAbsence->getNsam()==true and $oAbsence->getNspm()==true and $option == self::OPTION_DAY_THREE) return 1;
		}
		return 0;
	}
	//Get absence for student from object contract, a date and an option (1=AM or 2=PM)
	public function getStudentAbs($oContract,$oDate,$option){
		$oStudent = $this->getArrayZero($oContract->getStudents());
		if($oStudent==null) return $oStudent;
		$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if($oAbsence->getAmorpm()     == self::PERIOD_DAY_ALL){
                            return $oAbsence;
                        }
			elseif($oAbsence->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE){
                            return $oAbsence;
                        }
			elseif($oAbsence->getAmorpm() == self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO){
                            return $oAbsence;
                        }
		}
		return null;
	}
	//Get absence by student object, a date and an option (1=AM or 2=PM)
	public function getAbsenceByStudent($oStudent,$oDate,$option){
		if($oStudent==null) return $oStudent;
		$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if($oAbsence->getAmorpm()     == self::PERIOD_DAY_ALL){
                            return $oAbsence;
                        }
			elseif($oAbsence->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE){
                            return $oAbsence;
                        }
			elseif($oAbsence->getAmorpm() == self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO){
                            return $oAbsence;
                        }
		}
		return null;
	}
	/*
	*This function verify if there exists absence of the student
	*option=1 then for AM and option=2 then for PM
	*/
	private function isGroupAbsent($oSchedule,$oDate,$option){		
		$oGroup = $oSchedule->getGroup();
		if($oGroup==null) return false;
		$aAbsence = $this->getGroupAbsByDate($oGroup,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if($oAbsence->getAmorpm()== self::PERIOD_DAY_ALL){
                            return true;
                        }
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE) or ($oAbsence->getAmorpm()== self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO)){
                            return true;
                        }
		}		
		return false;
	}
	/*
	*This function verify if there exists absence of the group
	*option=1 then for AM and option=2 then for PM
	*/
	private function isStudentAbsent($oSchedule,$oDate,$option){
		$oContract = $oSchedule->getContracts();
		if($oContract==null) return false;
		$oStudent = $this->getArrayZero($oContract->getStudents());
		if($oStudent==null) return false;
		$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if($oAbsence->getAmorpm()== self::PERIOD_DAY_ALL){
                            return true;
                        }
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE) or ($oAbsence->getAmorpm()== self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO)){
                            return true;
                        }
		}		
		return false;
	}
	protected function getStudentAbsByDate($oStudent,$oDate=null){
		if($oDate==null) $oDate = $this->getToday();
		$oRepAbs = $this->getRepository('BoAdminBundle:Absences');
		$aAbsences = $oRepAbs->getStudentsAbsencesByDate($oStudent,$oDate);
		return $aAbsences; 
	}
	protected function getGroupAbsByDate($oGroup,$oDate=null){
		if($oDate==null) $oDate = $this->getToday();
		$oRepAbs = $this->getRepository('BoAdminBundle:AbsenceGroup');
		$aAbsences = $oRepAbs->getAbsencesByDate($oGroup,$oDate);
		return $aAbsences; 
	}
	protected function getStudentAbsByDates($oStudent,$oDate1,$oDate2){
		$oRepAbs = $this->getRepository('BoAdminBundle:Absences');
		$aAbsences = $oRepAbs->getStudentsAbsencesByDates($oStudent,$oDate1,$oDate2);
		return $aAbsences; 
	}
	private function getStudentAbsBySchedule($oStudent,$oSchedule){
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:Absences');
		$aAbsences = $oRepAbs->getStudentsAbsencesBySchedule($oStudent,$oSchedule);
		return $aAbsences; 
	}
	private function getArrayZero($aStudents){
		if(count($aStudents)==1) return $aStudents[0];
		return null;
	}
	private function existSubs($aSubstitution,$option,$idemployee){
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and $idemployee==$oSubstitution->getIdsubstitute()) return $oSubstitution;
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and $idemployee==$oSubstitution->getIdsubstitute()) return $oSubstitution;
		}
		return false;
	}
	private function existDaySubstitution($aSubstitution,$option,$idemployee){
		$aResult = array();
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and $idemployee==$oSubstitution->getIdsubstitute()) 					$aResult[] = $oSubstitution;
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and $idemployee==$oSubstitution->getIdsubstitute()) 					$aResult[] = $oSubstitution;
		}
		return $aResult;
	}
	protected function isWeekend($oDate){
		if($oDate->format("l")=="Saturday" or $oDate->format("l")=="Sunday") return true;
		else return false;
	}
	protected function existDaySchedule($oSchedule,$oDate){
		if($oSchedule==null) return 0;
		if($oSchedule->getMonday()==1 and $oDate->format("l")=="Monday"){
			return 1;
		}
		if($oSchedule->getTuesday()==1 and $oDate->format("l")=="Tuesday"){
			return 1;
		}
		if($oSchedule->getWednesday()==1 and $oDate->format("l")=="Wednesday"){
			return 1;
		}
		if($oSchedule->getThursday()==1 and $oDate->format("l")=="Thursday"){
			return 1;
		}
		if($oSchedule->getFriday()==1 and $oDate->format("l")=="Friday"){
			return 1;
		}
		if($oSchedule->getSaturday()==1 and $oDate->format("l")=="Saturday"){
			return 1;
		}
		return 0;	
	}
	private function activeDay($oSchedule,$oDate){
		if($oSchedule==null) return 0;
		if($oSchedule->getMonday()==1 and $oDate->format("l")=="Monday"){
			$oSchedule->setMonday(true);
		}
		if($oSchedule->getTuesday()==1 and $oDate->format("l")=="Tuesday"){
			$oSchedule->getTuesday(true);
		}
		if($oSchedule->getWednesday()==1 and $oDate->format("l")=="Wednesday"){
			$oSchedule->setWednesday(true);
		}
		if($oSchedule->getThursday()==1 and $oDate->format("l")=="Thursday"){
			$oSchedule->setThursday(true);
		}
		if($oSchedule->getFriday()==1 and $oDate->format("l")=="Friday"){
			$oSchedule->setFriday(true);
		}
		return $oSchedule;	
	}
	protected function actualizeScheduleDay($oSchedule,$oDate){
		//Update Monday
		if($oSchedule->getMonday()==1 and $oDate->format("l")!="Monday") $oSchedule->setMonday(false);
		elseif($oSchedule->getMonday()==0 and $oDate->format("l")=="Monday") $oSchedule->setMonday(true);
		//Update Tuesday
		if($oSchedule->getTuesday()==1 and $oDate->format("l")!="Tuesday") $oSchedule->setTuesday(false);
		elseif($oSchedule->getTuesday()==0 and $oDate->format("l")=="Tuesday") $oSchedule->setTuesday(true);
		//Update Wednesday
		if($oSchedule->getWednesday()==1 and $oDate->format("l")!="Wednesday") $oSchedule->setWednesday(false);
		elseif($oSchedule->getWednesday()==0 and $oDate->format("l")=="Wednesday") $oSchedule->setWednesday(true);
		//Update Thursday
		if($oSchedule->getThursday()==1 and $oDate->format("l")!="Thursday") $oSchedule->setThursday(false);
		elseif($oSchedule->getThursday()==0 and $oDate->format("l")=="Thursday") $oSchedule->setThursday(true);
		//Update Thursday
		if($oSchedule->getFriday()==1 and $oDate->format("l")!="Friday") $oSchedule->setFriday(false);
		elseif($oSchedule->getFriday()==0 and $oDate->format("l")=="Friday") $oSchedule->setFriday(true);
		return $oSchedule;	
	}
	protected function updateDay($oSubstitution){
		$startdate = $oSubstitution->getStartdate();
		$enddate = $oSubstitution->getEnddate();
		if($startdate->format("d-m-Y")==$enddate->format("d-m-Y")){
			return $this->actualizeScheduleDay($oSubstitution,$startdate);
		}
		return $oSubstitution;		
	}
	protected function getScheduledContract($oSchedule,$oDate=null){
		if($oDate==null) $oDate=$this->getToday();
		if($oSchedule==null) return $oSchedule;
		if($oSchedule->getContracts()==null) return null;
		$oContract =$oSchedule->getContracts();
		if(($oContract->getStatus()==1 or $oContract->getStatus()==2) and $oContract->getStartdate()<=$oDate and $oDate<=$oContract->getEnddate()){
			return $oContract;
		}
		return null;
	}
	protected function getScheduledGroup($oSchedule,$oDate=null){
		if($oDate==null) $oDate=$this->getToday();
		if($oSchedule==null) return $oSchedule;
		if($oSchedule->getGroup()==null) return null;
		$oGroup = $oSchedule->getGroup();
		if(($oGroup->getStatus()==1 or $oGroup->getStatus()==2) and $oGroup->getStartdate()<=$oDate and $oDate<=$oGroup->getEnddate()){
			return  $oGroup;
		}
		return null;
	}
	protected function getNmsoStatistic(){
		$aInprogress=$aUpcoming=$aArchived=array();
		$em = $this->getDoctrine()->getManager();
		$aWF = $em->getRepository('BoAdminBundle:Workfields')->findAll();
		$oRepContract = $em->getRepository('BoAdminBundle:Contracts');
		foreach($aWF as $oWF){
			$aInprogress[$oWF->getId()] = $oRepContract->getTotalByField($oWF->getId(),1);
			$aUpcoming[$oWF->getId()] = $oRepContract->getTotalByField($oWF->getId(),2);
			$aArchived[$oWF->getId()] = $oRepContract->getTotalByField($oWF->getId(),0);
		}
		return array($aInprogress,$aUpcoming,$aArchived);
	}
	protected function getPrices($enquiry){
		$em = $this->getDoctrine()->getManager();
		$oRep = $em->getRepository('BoAdminBundle:Prices');
		return $oRep->getPricesBy($enquiry);
	} 
	protected function getOtherPrices($enquiry){
		$em = $this->getDoctrine()->getManager();
		$oRep = $em->getRepository('BoAdminBundle:Prices');
		return $oRep->getPricesBy($enquiry);
	} 
	protected function getOrderRate($enquiry){
		$aPrices = $this->getPrices($enquiry);
		$startdate = $enquiry->getStartdate();
		$enddate = $enquiry->getEnddate();
		$hours = $resthour = intval($enquiry->getHours());
		$totalhours=0;
		$aResult=array();
		foreach($aPrices as $oPrice){

			if($startdate>=$oPrice->getStartdate() and $startdate<=$oPrice->getEnddate()){

				if($enddate>=$oPrice->getEnddate()){
					$endd = $oPrice->getEnddate();
				}else{
					$endd = $enddate;
				}
				$numberday = $this->getNumberDay($startdate,$endd);
				$hourperday = $enquiry->getHourperday();
				$realhour = $numberday*intval($hourperday);	
				$totalcost = $oPrice->getPrice()*$realhour;
				if($realhour>$resthour) $realhour = $resthour;
				
				if($totalhours<$hours){
					$aResult[] = array('startdate'=>$startdate,'enddate'=>$endd,'price'=>$oPrice->getPrice(),'hours'=>$realhour,'totalcost'=>$totalcost);
					$totalhours=$totalhours+$realhour;
				}
				$resthour = $hours - $totalhours;
			}
			if($enddate>=$oPrice->getEnddate()){
				$startdate=$this->getDatePlus($oPrice->getEnddate(),1);
			}
		}
		return $aResult;
	}
	protected function getInfoMail($label,$id){
		$em = $this->getDoctrine()->getManager();
		$mail = $em->getRepository('BoAdminBundle:Mails')->getInfoMail($label,$id);
		if(is_object($mail)) return $mail->getMessageen();
		if(isset($mail[0])) $mail[0]->getMessageen();
		return null;
	}
	protected function getParam($label,$id){
		$em = $this->getDoctrine()->getManager();
		return $em->getRepository('BoAdminBundle:Param')->getParam($label,$id);
	}
	protected function getFilename($path,$bordereau){
		return sprintf("%s/%s",$path,$bordereau);
	} 
	protected function updateStatusBy($entity,$status){
		$entity->setStatus($status);
		return $this->updateEntity($entity);			
	}
	protected function getActiveContract($aContract){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		foreach($aContract as $oContract){
			if($this->isActiveContract($oContract)){
				$aResult[] = $oContract;
			}
		}
		return $aResult;
	}
	protected function isUpcomingContract($oContract){
		$today = new \DateTime(date("d-m-Y"));
		if($oContract->getStatus()==2 and $oContract->getStartdate()>=$today){
			return true;
		}
		return false;
	}
	protected function isActiveContract($oContract){
		$today = new \DateTime(date("d-m-Y"));
		if($oContract->getStatus()==1 and $oContract->getStartdate()<=$today and $today<=$oContract->getEnddate()){
			return true;
		}
		return false;
	}
	protected function getActiveGroup($aGroup){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		foreach($aGroup as $oGroup){
			if($this->isActiveContract($oGroup)){
				$aResult[] = $oGroup;
			}
		}
		return $aResult;
	}
	protected function isActiveGroup($oGroup){
		$today = new \DateTime(date("d-m-Y"));
		if($oGroup->getStatus()==1 and $oGroup->getStartdate()<=$today and $today<=$oGroup->getEnddate()){
			return true;
		}
		return false;
	}
	protected function isUpcomingGroup($oGroup){
		$today = new \DateTime(date("d-m-Y"));
		if($oGroup->getStatus()==2 and $oGroup->getStartdate()>=$today){
			return true;
		}
		return false;
	}
	protected function getTeacherBy($oEmployee){
		if($oEmployee) return $oEmployee->getFirstname()." ".$oEmployee->getName();
		return null;
	}
	protected function getEmployeeById($idemployee){
		$em = $this->getDoctrine()->getManager();
		return $em->getRepository('BoAdminBundle:Employee')->find($idemployee);
	}
	protected function getTeacherNameById($idemployee){
		$oEmployee = $this->getEmployeeById($idemployee);
		return $this->getTeacherBy($oEmployee);
	}
	protected function getEmployeeNameById($idemployee){
		$idemployee = intval($idemployee);
		if($idemployee==0) return null;
		return $this->getTeacherNameById($idemployee);
	}
	protected function getContractForAbsence($oAbsence){
		$aResult = array();
		$oEmployee = $oAbsence->getEmployee();
		$aContracts = $oEmployee->getContracts();
		$aContracts = $this->getActiveContract($aContracts);
		foreach($aContracts as $oContract){
			//$oTraining = $this->getContractTrainning($oContract);
			$aResult[] = $oContract;	
		}
		return $aResult;
	}
	protected function getGroupForAbsence($oAbsence){
		$aResult = array();
		$oEmployee = $oAbsence->getEmployee();
		$aGroups = $this->getActiveContract($oEmployee->getGroup());
		foreach($aGroups as $oGroup){
			$oTraining = $this->getContractTrainning($oGroup);
			$aResult[] = $oGroup;		
		}
		return $aResult;
	}
	protected function getContractTrainning($contract){
		$em = $this->getDoctrine()->getManager();
		$aTraining=$em->getRepository('BoAdminBundle:Training')->findBy(array('contracts'=>$contract));
		if(isset($aTraining[0])) return $aTraining[0];
		return null;
	}
	protected function getGroupTrainning($group){
		$em = $this->getDoctrine()->getManager();
		$aTraining=$em->getRepository('BoAdminBundle:Training')->findBy(array('idgroup'=>$group->getId()));
		if(isset($aTraining[0])) return $aTraining[0];
		return null;
	}
	protected function getBordereauNumber($enquiry){
		$em = $this->getDoctrine()->getManager();
		$oParam=$em->getRepository('BoAdminBundle:Param')->find(20);
		//bL is equal to 1 if language is english else 2
		$bL = $enquiry->getLanguage()=="French"?1:2;
		//bF is equal to 1 if language is english else 2
		$bF = $enquiry->getFtorpt()=="Full Time"?1:2;
		if($oParam){
			$number_order = $oParam->getValue();
			$oParam->setValue(intval($oParam->getValue())+1);
			$this->updateEntity($oParam);
		}else{
			$number_order = rand(100,1000);
		}
		return sprintf("%s%s%s%s",$number_order,date("Ymd"),$bL,$bF);
	}
	protected function getSoumisstionType(){
		return array('1' => 'NMSO', '2' => 'Bon de commande/Order form', '3' => 'Private contract french/Contrat privé français', '4' => 'Private contract english/Contrat privé anglais');
	}
	protected function getPdfPath($dir){
		return sprintf("%s/%s",$this->get('app.pdfpath_manager')->getPath(),$dir);
	}
	protected function getStudentBy($contract){
		$aStudent=$contract->getStudents();
		if(count($aStudent)==1 and $oStudent=$aStudent[0]) return $oStudent->getFirstname()." ".$oStudent->getName();
		$oGroup = $contract->getGroup();
		if($oGroup and $oGroup->getName()) return $oGroup->getName();
		return null;
	}
	protected function getObjectStudentBy($contract){
		$aStudent=$contract->getStudents();
		if(count($aStudent)==1 and $oStudent=$aStudent[0]) return $oStudent;
		elseif(count($aStudent)>1) return $aStudent;
		return null;
	}
	protected function getOnlyStudentBy($contract){
		$aStudent=$contract->getStudents();
		if(count($aStudent)==1 and $oStudent=$aStudent[0]) return $oStudent;
		return null;
	}
	protected function getStudentByAgenda($agenda){
		$contract = $agenda->getContracts();
		if($contract) return $contract->getStudents();
		$oGroup = $agenda->getGroup();
		if($oGroup){
			if(count($oGroup->getStudents())>0) return $oGroup->getStudents();
			return $this->getStudentsByGroup($oGroup);
		}
		return null;
	}
	protected function getStudentByContract($contract){
		$aStudent=$contract->getStudents();
		if(count($aStudent)==1 and $oStudent=$aStudent[0]) return $oStudent->getFirstname()." ".$oStudent->getName();
		$oGroup = $contract->getGroup();
		if($oGroup and $oGroup->getName()) return $oGroup->getName();
		return null;
	}
	protected function getStudentObjectBy($contract){
		$aStudent=$contract->getStudents();
		if(count($aStudent)==1 and $oStudent=$aStudent[0]) return $oStudent;
		return null;
	}
	protected function getRevisionBy($contract){
		$em = $this->getDoctrine()->getManager();
		$aRevision=$em->getRepository('BoAdminBundle:Revision')->findBy(array('idcontracts'=>$contract->getId()));
		if(isset($aRevision[0])) return $aRevision[0];
		return null;
	}
	protected function getEvalField($evaluation){
		if($evaluation->getLanguages()=="French"){
			if($evaluation->getTypetime()=="Full Time"){
				if($evaluation->getTypeoftraining()=="Individual") return 7;
				if($evaluation->getTypeoftraining()=="Group") return 1;
				if($evaluation->getTypeoftraining()=="semi-private") return 0;
			}else{
				return 9;
			}
		}
		if($evaluation->getLanguages()=="English"){
			if($evaluation->getTypetime()=="Full Time"){
				if($evaluation->getTypeoftraining()=="Individual") return 8;
				if($evaluation->getTypeoftraining()=="Group") return 2;
				if($evaluation->getTypeoftraining()=="semi-private") return 0;
			}else{
				return 0;
			}
		}
		
	}
	protected function getSoumissionType($enquiry){
		if($enquiry->getTypecontract()=="Service Contract"){ 
			if($enquiry->getPrivateorgroup()=="Group") return 1; 
			else return 2;
		}
		if($enquiry->getTypecontract()=="private" or $enquiry->getTypecontract()=="Private"){
			if($enquiry->getLanguage()=="French") return 3;
			else return 4;
		}
		return 1;
	}
	//Employee functions
	protected function getEmployeeGroup($employee){
		$aResult = array();
		$aGroup = $employee->getGroup();
		$today = new \DateTime(date("d-m-Y"));
		foreach($aGroup as $oGroup){
			if($oGroup->getStartdate()<=$today and $oGroup->getEnddate()>=$today) $aResult[] = $oGroup;			
		} 
		if(isset($aResult[0])) return $aResult;
		$aContract = $employee->getContracts();
		foreach($aContract as $oContract){
			if($oContract->getStatus()!=1) continue;
			if($oContract->getGroup() and $oContract->getWorkfields()->getId()==1 or $oContract->getWorkfields()->getId()==2){
				$oGroup = $oContract->getGroup();
				$aResult[] = $oGroup;
			}
		}
		return $aResult;
	}
	protected function getEmployeeClearance($employee){
		$em = $this->getDoctrine()->getManager();
		return $em->getRepository('BoAdminBundle:SecurityCote')->findBy(array('employee'=>$employee));
	}
	//get the employee's document : return array
	protected function getEmppjs($employee){
		$em = $this->getDoctrine()->getManager();
		return $em->getRepository('BoAdminBundle:Emppj')->findBy(array('employee'=>$employee));
	}
	//get the employee's admin hours : return array
	protected function getEmployeeAdHour($employee,$total=null,$offset=null){
		$em = $this->getDoctrine()->getManager();
		return $em->getRepository('BoAdminBundle:Tadmin')->findBy(array('employee'=>$employee),array('id' => 'desc'),$total,$offset);
	}		
	//get the employee's admin hours : return array
	protected function getEmployeeAbsences($employee,$total=null,$offset=null){
		$em = $this->getDoctrine()->getManager();
		if($total!=null or $total!=0){
			return $em->getRepository('BoAdminBundle:AbsEmp')->findBy(array('employee'=>$employee),array('id' => 'desc'),$total,$offset);
		}
		return $em->getRepository('BoAdminBundle:AbsEmp')->findBy(array('employee'=>$employee),array('id' => 'desc'));
	}
	protected function authorizedAdHour($employee){
		$aAdhours = $this->getEmployeeAdHour($employee);
		foreach($aAdhours as $oAdHours){
			$today = new \DateTime(date("d-m-Y"));
			if($oAdHours->getStartdate()<=$today and $today<=$oAdHours->getEnddate()){
				return 1;
			}
			return 0;
		}
	}
	protected function createAbsence($absence){
		$aContracts = $this->getContractForAbsence($absence);
		$aGroup = $this->getGroupForAbsence($absence);
		foreach($aContracts as $oContract){
			//Avoid to the system to bug with the existing line
			if($this->existEntity($oContract,$absence->getContracts())==true) continue;
			//get the schedule for the employee who did this absences
			$oSchedule = $this->getCScheduleByAbsence($absence->getEmployee(),$oContract);
			if($oSchedule==null) continue;
			//take only the contract impacted
			if($absence->getAmorpm()== PERIOD_DAY_ALL or $oSchedule->getAmorpm()=="AM & PM" or $oSchedule->getAmorpm()== PERIOD_DAY_ALL or $oSchedule->getAmorpm()==$absence->getAmorpm()){ $absence->addContract($oContract);}
		}
		foreach($aGroup as $oGroup){
			//Avoid to the system to bug with the existing line
			if($this->existEntity($oGroup,$absence->getGroup())==true) continue;
			//get the schedule for the employee who did this absences
			$oSchedule = $this->getGScheduleByAbsence($absence->getEmployee(),$oGroup);
			if($oSchedule==null) continue;
			if($absence->getAmorpm()== PERIOD_DAY_ALL or $oSchedule->getAmorpm()=="AM & PM" or $oSchedule->getAmorpm()==$absence->getAmorpm()){ $absence->addGroup($oGroup);}
		}
		return $this->updateEntity($absence);
	}
	protected function getCpEmailByAbsence($absence){
		$emails = "";
		$aContracts = $this->getContractForAbsence($absence);
		$aGroup = $this->getGroupForAbsence($absence);
		foreach($aContracts as $oContract){
			$oAvisor = $oContract->getAdvisor();
			if($oAvisor and $oAvisor->getEmail()!=null) $emails = $emails.$oAvisor->getEmail().",";
		}
		foreach($aGroup as $oGroup){
			$oAvisor = $oGroup->getAdvisor();
			if($oAvisor and $oAvisor->getEmail()!=null) $emails = $emails.$oAvisor->getEmail().",";
		}
		return $emails;
	}
	protected function existSubstitution($absence){
		$aEcontract = $aEgroup = array();
		$aContract = $absence->getContracts();
		$aGroup = $absence->getGroup();
		foreach($aContract as $oContract){
			$aEcontract[$oContract->getId()] = $this->getRepository('BoAdminBundle:Substitution')->existContSubs($absence,$oContract->getId());
		}
		foreach($aGroup as $oGroup){
			$aEgroup[$oGroup->getId()] = $this->getRepository('BoAdminBundle:Substitution')->existGroupSubs($absence,$oGroup->getId());
		}
		return array($aEcontract,$aEgroup);
	}
	//return a tableau of bit to check if the add substitution must be displayed
	protected function getSBD($absence){
		$aEcontract = $aEgroup = array();
		$aContract = $absence->getContracts();
		$aGroup = $absence->getGroup();
		foreach($aContract as $oContract){
			$aEcontract[$oContract->getId()] = 1;
		}
		foreach($aGroup as $oGroup){
			$aEgroup[$oGroup->getId()] = 1;
		}
		return array($aEcontract,$aEgroup);
	}
	
	protected function getSubsButDisp($aSubstitution,$oAbsence){
		if(count($aSubstitution)==0) return $this->getSBD($oAbsence);
		$aDate = $this->getScheduleDate($aSubstitution);
		$aContract = $oAbsence->getContracts();
		foreach($aContract as $oContract){
			if(isset($aDate['start']) and $aDate['start']==$oAbsence->getStartdate() and isset($aDate['end']) and $aDate['end']==$oAbsence->getEnddate()){

				return $this->checkAllSubs($aSubstitution,$oAbsence);
			}
		}

		$aEcontract = $aEgroup = array();
		foreach($aSubstitution as $oSubstitution){
			$idcontract = $oSubstitution->getIdcontract();
			$idgroup = $oSubstitution->getIdgroup();
			if($idcontract!=null){
				$aEcontract[$idcontract]=$this->getSubsButBit($oSubstitution,$oAbsence,$idcontract);
			}elseif($idgroup!=null){
				$aEgroup[$idgroup]=$this->getSubsButBit($oSubstitution,$oAbsence,$idcontract,$idgroup);
			}
		}
		return array($aEcontract,$aEgroup);
	}
	
	protected function getSubsBDForCont($oAbsence){
		$aResult = array();
		$aContract = $oAbsence->getContracts();
		foreach($aContract as $oContract){
			$aSubstitution = $this->getSusbByAbsCont($oAbsence,$oContract);
			if(count($aSubstitution)==0) $aResult[$oContract->getId()]=1;
			$aDate = $this->getScheduleDate($aSubstitution);
			if(isset($aDate['start']) and $aDate['start']==$oAbsence->getStartdate() and isset($aDate['end']) and $aDate['end']==$oAbsence->getEnddate()){
				$aResult[$oContract->getId()]=0;
			}else{
				$aResult[$oContract->getId()]=1;
			}
		}
		return $aResult;
	}
	protected function getSubsBDForGroup($oAbsence){
		$aResult = array();
		$aGroup = $oAbsence->getGroup();
		foreach($aGroup as $oGroup){
			$aSubstitution = $this->getSusbByAbsGroup($oAbsence,$oGroup);
			if(count($aSubstitution)==0) $aResult[$oGroup->getId()]=1;
			$aDate = $this->getScheduleDate($aSubstitution);
			if(isset($aDate['start']) and $aDate['start']==$oAbsence->getStartdate() and isset($aDate['end']) and $aDate['end']==$oAbsence->getEnddate()){
				$aResult[$oGroup->getId()]=0;
			}else{
				$aResult[$oGroup->getId()]=1;
			}
		}
		return $aResult;
	}
	private function getSubsButBit($oSubstitution,$oAbsence,$idcontract,$idgroup=null){
		if($oSubstitution->getStartdate()!=$oSubstitution->getEnddate())
		$subdiff = $this->getDiffDate($oSubstitution->getStartdate(),$oSubstitution->getEnddate());
		else $subdiff = 1;
		if($oAbsence->getStartdate()!=$oAbsence->getEnddate())
		$absdiff = $this->getDiffDate($oAbsence->getStartdate(),$oAbsence->getEnddate());
		else $absdiff = 1;
		if($subdiff<$absdiff){
			return 1;
		}else{
			$subhour = $this->getTotalHourBy($oSubstitution);
			if($idcontract!=null){
				$oContract = $this->getRepository('BoAdminBundle:Contracts')->find($idcontract);
				$oSchedule=$this->getCScheduleByAbsence($oAbsence->getEmployee(),$oContract);
			}elseif($idgroup!=null){
				$oGroup = $this->getRepository('BoAdminBundle:Group')->find($idgroup);
				$oSchedule=$this->getGScheduleByAbsence($oAbsence->getEmployee(),$oGroup);
			}
			if($oSchedule) $abshour = $this->getTotalHourBy($oSchedule);
			if(isset($abshour) and $subhour<$abshour){ 
				return 1;
			}
		}
		return 0;
	}
	//Verify if all the substitution cover the absence
	private function checkAllSubs($aSubstitution,$oAbsence){
		$aEcontract = $aEgroup = array();
		if($this->checkSameId($aSubstitution)==true){
			$sub_hour = $this->getHourSubstitution($aSubstitution);
			$oContract = $this->getContractFrom($aSubstitution);
			$oGroup = $this->getGroupFrom($aSubstitution);
			if($oAbsence and $oContract){
				$oSchedule = $this->getCScheduleByAbsence($oAbsence->getEmployee(),$oContract);
				if($oSchedule!=null and $oSchedule->getHourperday()==$sub_hour){
					$aEcontract[$oContract->getId()] = 0;
				}
			}
			if($oAbsence and $oGroup){
				$oSchedule = $this->getGScheduleByAbsence($oAbsence->getEmployee(),$oGroup);
				if($oSchedule!=null and $oSchedule->getHourperday()==$sub_hour){
					$aEgroup[$oGroup->getId()] = 0;
				}
			}
		}
		return array($aEcontract,$aEgroup);
	}
	//Check if all substitution are for the same contract or the same group
	private function checkSameId($aSubstitution){
		$aEcontract = $aEgroup = array();
		$count=count($aSubstitution);
		foreach($aSubstitution as $oSubstitution){
			$idcontract = $oSubstitution->getIdcontract();
			$idgroup = $oSubstitution->getIdgroup();
			if($idcontract!=null){
				$aEcontract[]=$idcontract;
			}elseif($idgroup!=null){
				$aEgroup[]=$idgroup;
			}
		}
		if(count($aEcontract)==$count or count($aEgroup)==$count){
			 return true;	
		}	
		return false;
	}
	//get date scheduled for the substitution
	private function getScheduleDate($aSubstitution){
		$aDates = array();
		foreach($aSubstitution as $oSubstitution){
			if(isset($aDates['start']) and $aDates['start']>$oSubstitution->getStartdate()){
				$aDates['start'] = $oSubstitution->getStartdate();
			}else{
				$aDates['start'] = $oSubstitution->getStartdate();
			}
			if(isset($aDates['end']) and $aDates['end']>$oSubstitution->getEnddate()){
				$aDates['end'] = $oSubstitution->getEnddate();
			}else{
				$aDates['end'] = $oSubstitution->getEnddate();
			}
	
		}
		return $aDates;
	}
	//Check if all substitution are for the same contract or the same group
	private function getHourSubstitution($aSubstitution){
		$hour = 0;
		foreach($aSubstitution as $oSubstitution){
			$hour=$hour+$oSubstitution->getHour();
		}
		return $hour;
	}
	//get contract from substitution
	private function getContractFrom($aSubstitution){
		foreach($aSubstitution as $oSubstitution){
			$idcontract = $oSubstitution->getIdcontract();
			if($idcontract!=null){
				return $this->getRepository('BoAdminBundle:Contracts')->find($idcontract);
			}
		}
		return null;
	}
	//get group from substitution
	private function getGroupFrom($aSubstitution){
		foreach($aSubstitution as $oSubstitution){
			$idgroup = $oSubstitution->getIdgroup();
			if($idgroup!=null){
				return $this->getRepository('BoAdminBundle:Group')->find($idgroup);
			}
		}
		return null;
	}
	//Get substitution by absence entity
	protected function getSusbByAbsence($absence){
		return $this->getRepository('BoAdminBundle:Substitution')->findBy(array('idabsence'=>$absence->getId()));
	}
	//Get substitution by absence entity
	protected function getSusbByAbsCont($absence,$contract){
		return $this->getRepository('BoAdminBundle:Substitution')->findBy(array('idabsence'=>$absence->getId(),'idcontract'=>$contract->getId()));
	}
	//Get substitution by absence entity
	protected function getSusbByAbsGroup($absence,$group){
		return $this->getRepository('BoAdminBundle:Substitution')->findBy(array('idabsence'=>$absence->getId(),'idgroup'=>$group->getId()));
	}
	//Verify if the absence of day are covered
	//If 1 there are the substitution for all contract of the teacher absent
	protected function getAbsSubs($absences){
		$aResult = array();
		foreach($absences as $absence){
			//$nbcont = count($absence->getContracts())+count($absence->getGroup());
			$aSubstitution = $this->getSusbByAbsence($absence);
			if(count($aSubstitution)>0) $aResult[$absence->getId()] = 1;
			else $aResult[$absence->getId()] = 0;
		}
		return $aResult;
	}
	private function getAmEventDesc($oEntity,$employee,$student,$room,$sAdvisor,$option,$oDate=null,$sStatusAbsence=null,$sStudentAbsence=null,$sSubstitution=null){
		$sEventLib =  $oEntity->getStartam()->format("H:i")." - ".$oEntity->getEndam()->format("H:i")."<br>".$employee->getFirstname()." ".$employee->getName().$this->getDayAction($oDate).$student.$room.$this->transCp().$sAdvisor;
		if($option == self::OPTION_DAY_TWO) $sEventLib = $sEventLib."<br><br>".$this->getSubsSubstitute($oEntity).$this->transReplace().$this->getSubstitutionHolder($oEntity);
		if($option == self::OPTION_DAY_ONE and $this->isHolidaysBy($oDate,$oEntity,1)==true) $sEventLib = $sEventLib."<br><br>".$this->getHoliday($oDate,1);
		if($sStatusAbsence!=null) $sEventLib = $sEventLib."<br><br>".$sStatusAbsence;
		if($sStudentAbsence!=null) $sEventLib = $sEventLib."<br><br>".$sStudentAbsence;
		//if($sSubstitution!=null) $sEventLib = $sEventLib."<br><br>".$sSubstitution;
		return $sEventLib;
	}
	private function getPmEventDesc($oEntity,$employee,$student,$room,$sAdvisor,$option,$oDate=null,$sStatusAbsence=null,$sStudentAbsence=null,$sSubstitution=null){
		$sEventLib = $oEntity->getStartpm()->format("H:i")." - ".$oEntity->getEndpm()->format("H:i")."<br>".$employee->getFirstname()." ".$employee->getName().$this->getDayAction($oDate).$student.$room.$this->transCp().$sAdvisor;
		if($option == self::OPTION_DAY_TWO){
			$sEventLib = $sEventLib."<br><br>".$this->getSubsSubstitute($oEntity).$this->transReplace().$this->getSubstitutionHolder($oEntity);
		}
		if($option == self::OPTION_DAY_ONE and $this->isHolidaysBy($oDate,$oEntity,2)==true) $sEventLib = $sEventLib."<br><br>".$this->getHoliday($oDate,2);
		if($sStatusAbsence!=null) $sEventLib = $sEventLib."<br><br>".$sStatusAbsence;
		if($sStudentAbsence!=null) $sEventLib = $sEventLib."<br><br>".$sStudentAbsence;
		if($sSubstitution!=null) $sEventLib = $sEventLib."<br><br>".$sSubstitution;
		return $sEventLib;		
	}
	private function getDayAction($oDate){
		$lang = $this->getLang();
		$label = $lang=="en"?" teach ":" enseigne ";
		if($oDate!=null){
			if($oDate==$this->getToday()){
				$label = $lang=="en"?" teach ":" enseigne ";
			}elseif($oDate<$this->getToday()){
				$label = $lang=="en"?" taught ":" a enseigné ";
			}elseif($oDate>$this->getToday()){
				$label = $lang=="en"?" will teach ":" enseignera ";
			}
		}
		return $label; 

	}
	private function getTransat(){
		return $this->getLang()=="en"?" at ":" au ";
	}
	private function getTransBy(){
		return $this->getLang()=="en"?" by ":" par ";
	}
	private function getTransCancel(){
		return $this->getLang()=="en"?" (Cancel) ":" (Annulé) ";
	}
	protected function getHoliday($oDate,$option){
		$return = $this->getLang()=="en"?" Holidays: ":" Jour férié: ";
		$oHolidays = $this->getHolidaysBy($oDate,$option);
		if($oHolidays){
			$detail = $oHolidays->getDesignation();
			if($oHolidays->getAmorpm()     == self::PERIOD_DAY_ALL){
                            return $return.$detail;
                        }
			elseif($oHolidays->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE){
                            return $return.$detail;
                        }
			elseif($oHolidays->getAmorpm() == self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO){
                            return $return.$detail;
                        }
		}
		return null;
	}
	protected function getHolidaysBool($oDate,$option){
		$oHolidays = $this->getHolidaysBy($oDate,$option);
		if($oHolidays){
			if($oHolidays->getAmorpm()     == self::PERIOD_DAY_ALL){
                            return true;
                        }
			elseif($oHolidays->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE){
                            return true;
                        }
			elseif($oHolidays->getAmorpm() == self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO){
                            return true;
                        }
		}
		return false;
	}
	private function transCp(){
		return $this->getLang()=="en"?", advisor ":", CP ";
	}
	private function transReplace(){
		return $this->getLang()=="en"?" substitute ":" remplace ";
	}
	protected function getGroupStatus(){
		return array('0'=>"Closed", '1'=>"In progress", '2'=>"upcomming", '3'=>"outstanding", '4'=>"Canceled");
	}
	protected function getCampusBySchedule($oSchedule,$oContract=null){
		if($oContract==null){
			$oGroup = $oSchedule->getGroup();
			if($oGroup){
				$aContract = $oGroup->getContracts(); 
				if(isset($aContract[0])) $oContract = $aContract[0];
			}else{
				$oContract = $oSchedule->getContracts();
			}
		}
		if($oContract and $oContract->getCampus()!=null){
			return $oContract->getCampus()->getProvince();
		} 
		return null;	
	}
	/*
	* Return province by Schedule or by contract when contract exists
	*/
	protected function getProvinceBySchedule($oSchedule,$oContract=null){
		if($oContract==null) $oContract = $oSchedule->getContracts();
		if($oContract and $oContract->getAdresse()!=null){
			$sAddress = strtolower($oContract->getAdresse());
			if(strpos($sAddress,"gatineau")!=false){
				return "Québec";
			}
			if(strpos($sAddress,"ottawa")!=false){
				return "Ontario";
			}
		} 
		return null;	
	}
	protected function isHolidaysBy($oDate,$oSchedule,$option=null){
		$oHoliday = $this->getHolidaysBy($oDate,$option);
		if($oHoliday==null) return false;
		if($oHoliday->getProvince()=="All") return true;
		$province = $this->getCampusBySchedule($oSchedule);
		if($province!=null and $province==$oHoliday->getProvince()) return true;
		$province = $this->getProvinceBySchedule($oSchedule);
		if($province!=null and $province==$oHoliday->getProvince()) return true;
		$province = $this->getAdminProvince($oSchedule);
		if($province==null) return false;
		if($province=="All") return true;
		if($province!=null and $province==$oHoliday->getProvince()) return true;
		return false;
	}
	protected function isHolidaysByContract($oDate,$oContract,$option=null){
		$oHoliday = $this->getHolidaysBy($oDate,$option);
		if($oHoliday==null) return false;
		if($oHoliday->getProvince()=="All") return true;
		$province = $this->getCampusBySchedule(null,$oContract);
		if($province!=null and $province==$oHoliday->getProvince()) return true;
		$province = $this->getProvinceBySchedule(null,$oContract);
		if($province!=null and $province==$oHoliday->getProvince()) return true;
		$province = $this->getAdminProvince($oSchedule);
		if($province==null) return false;
		if($province=="All") return true;
		if($province!=null and $province==$oHoliday->getProvince()) return true;
		return false;
	}
	protected function getHolidaysBy($oDate,$option=null){
		$oRep = $this->getRepository('BoAdminBundle:Holidayslist');
		$aHolidays =$oRep->getByDate($oDate);
		if($option==null and count($aHolidays)>0) return $aHolidays[0];
		if(isset($aHolidays[0]) and $aHolidays[0]->getAmorpm() == self::PERIOD_DAY_ALL){
                    return $aHolidays[0];
                }
		elseif(isset($aHolidays[0]) and $aHolidays[0]->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE){
                    return $aHolidays[0];
                }
		elseif(isset($aHolidays[0]) and $aHolidays[0]->getAmorpm() == self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO){
                    return $aHolidays[0];
                }
		elseif(isset($aHolidays[0]) and $aHolidays[0]->getAmorpm() == self::PERIOD_DAY_ALL and $option == self::OPTION_DAY_THREE){
                    return $aHolidays[0];
                }
		return null;
	}
	protected function getAdminProvince($oSchedule){
		$oRepTC = $this->getRepository('BoAdminBundle:TeamContacts');
		$oEmployee = $oSchedule->getEmployee();
		//Get administration member's contact
		$aTeamContact = $oRepTC->findBy(array('employee'=>$oEmployee));
		//If there is not existing return null 
		if(count($aTeamContact)==0) return null;
		$oTeamContact = $aTeamContact[0]; 
		//Get the location of administration member
		$sLocation = $oTeamContact->getLocation();
		$aLocation = explode("&",$sLocation);
		if(count($aLocation)>1) return "All";
		elseif(count($aLocation)==1){
			$sVille = $aLocation[0];
			if(trim($sVille)=="Ottawa") return "Ontario";
			else return "Québec";
		}
		return null;
	}
	// update contrattype for group entity: group entity in parameters
	protected function updateContractTypeForGroup($oGroup){
		$aContract = $this->getContractByGroup($oGroup);
		if(isset($aContract[0]) and $oContract=$aContract[0] and $oGroup->getTypecontract()==null){
			$oTypeContract = $oContract->getTypecontract();
			$oGroup->setTypecontract($oTypeContract);
			return $this->updateEntity($oGroup);
		}
		return null;
	}
	// update contrattype for group entity -> group entity and contract entity
	protected function updateCTypeForGroup($oGroup,$oContract){
		if($oGroup==null or $oContract==null) return $oGroup;
		$oTypeContract = $oContract->getTypecontract();
		$oGroup->setTypecontract($oTypeContract);
		return $this->updateEntity($oGroup);
	}
	/*
	update status for group
	parameter: group entity
	*/
	protected function updateStatusForGroup($oGroup){
		$aContract = $this->getContractByGroup($oGroup);
		if(isset($aContract[0]) and $oContract=$aContract[0] and ($oContract->getStatus()==0 or $oContract->getStatus()==1 or $oContract->getStatus()==2)){
			$oGroup->setStatus($oContract->getStatus());
			return $this->updateEntity($oGroup);
		}
		return null;
	}
	/*
	update status for group
	parameters: group entity and contract entity
	*/
	protected function updateStatusForGroupBis($oGroup,$oContract){
		if($oGroup==null or $oContract==null) return $oGroup;
		$oGroup->setStatus($oContract->getStatus());
		return $this->updateEntity($oGroup);
	}
	/*
	update status and type contract for a group
	parameters: group entity and contract entity
	*/
	protected function updateStcForGroup($oGroup,$oContract){
		if($oGroup==null or $oContract==null) return $oGroup;
		$oTypeContract = $oContract->getTypecontract();
		$oGroup->setTypecontract($oTypeContract);
		$oGroup->setStatus($oContract->getStatus());
		return $this->updateEntity($oGroup);
	}
	protected function formatDate($aDates){
		$aFDates=array();
		foreach($aDates as $oDate){
			$aFDates[]=$this->get('translator')->trans($oDate->format("D"))." ".$oDate->format("d-m-Y");		
		}
		return $aFDates;
	}
	//update teacher schedule after a contract revision
	//return 1 if updating successful, 0 else
	protected function updateSchedule($oContract,$revision){
		$em = $this->getDoctrine()->getManager();
		$rep = $em->getRepository('BoAdminBundle:Agenda');
		if($oContract->getGroup() and $oContract->getWorkfields() and ($oContract->getWorkfields()->getId()==1 or $oContract->getWorkfields()->getId()==2)) return 0;
		$aSchedule = $rep->getScheduleForContractBis($oContract);
		foreach($aSchedule as $oSchedule){
			$oSchedule->setEnddate($revision->getEnddate());
			$this->updateEntity($oSchedule);
		}
		return 1;	
	}
	//Check if a contract is closed or archived
	//With the id contract as a parameter 
	protected function isArchived($oContract){
		if($oContract and $oContract->getStatus()==0) return true;
		return false;
	}
	//Check teacher availability : return 1 when teacher available 0 else
	public function getAvailability($oEmployee,$oContract){
		$em = $this->getDoctrine()->getManager();
		$aContract = $this->getContractsBy($oEmployee);
		foreach($aContract as $oContracts){
		}
		$aSchedule = $em->getRepository('BoAdminBundle:Agenda')->getTScForContract($aContract,$oEmployee);	
		foreach($aSchedule as $oSchedule){
		}
		if(count($aContract)==0 and count($aSchedule)==0) return 1;
		$aTraining=$em->getRepository('BoAdminBundle:Training')->findBy(array('contracts'=>$oContract));
	}
	protected function getHighEndAm($aAgenda){
		$higham = 12; 
		foreach($aAgenda as $aArray){
			if(isset($aArray['endam']) and $aArray['endam']>$higham) $higham=$aArray['endam'];
		}
		return $higham;
	}
	protected function getAllScheduleBy($idemployee){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		$aSchedule = $this->getScheduleByEmployee($idemployee);
		foreach($aSchedule as $oSchedule){
			if($this->isArchived($oSchedule->getContracts())==true) continue;
			if(($oSchedule->getStartdate()<=$today and $today<=$oSchedule->getEnddate()) or $oSchedule->getStartdate()>$today)
			$aResult[] = $oSchedule;
		}
		return $aResult;
	}
	protected function getScheduleWithIdBy($idemployee){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		$aSchedule = $this->getScheduleByEmployee($idemployee);
		foreach($aSchedule as $oSchedule){
			if($this->isArchived($oSchedule->getContracts())==true) continue;
			if(($oSchedule->getStartdate()<=$today and $today<=$oSchedule->getEnddate()) or $oSchedule->getStartdate()>$today)
			$oContract = $oSchedule->getContracts();
			if($oContract) $aResult[$oContract->getId()] = $oSchedule;
		}
		return $aResult;
	}
	protected function getCurrentScheduleBy($oEmployee){
		$oRepAgenda = $this->getRepository('BoAdminBundle:Agenda');
		return $oRepAgenda->getScheduleForEmployee($oEmployee);
	}
	protected function getCurrentEmployeeSchedule($oEmployee){
		return $this->getCurrentScheduleBy($oEmployee);
	} 
	protected function getAllEmployeeSchedule($oEmployee){
		return $this->getAllScheduleBy($oEmployee);
	} 
	protected function getCurrentSchedule($idemployee){
		$oEmployee = $this->getEmployeeById($idemployee);
		$aSchedule = $this->getCurrentScheduleBy($oEmployee);
	}
	protected function getAllSchedule($idEmployee){
		$oEmployee = $this->getEmployeeById($idemployee);
		$aSchedule = $this->getAllScheduleBy($oEmployee);
	}
	//Return true if the teacher is vailable and false else
	protected function isAvailable($idmployee,$teacherSchedule){
		$oEmployee = $this->getEmployeeById($idemployee);
		$aSchedule = $this->getCurrentScheduleBy($oEmployee);
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getStartdate()>$teacherSchedule->getEnddate() or $teacherSchedule->getStartdate()>$oSchedule->getEnddate()) return true;
			elseif(($oSchedule->getStartam()>$teacherSchedule->getEndam() or $teacherSchedule->getStartam()>$oSchedule->getEndam()) and ($oSchedule->getStartpm()>$teacherSchedule->getEndpm() or $teacherSchedule->getStartpm()>$oSchedule->getEndpm())) return true;
			elseif($oSchedule->getMonday()!=$teacherSchedule->getMonday() and $oSchedule->getTuesday()!=$teacherSchedule->getTuesday()  and $oSchedule->getWednesday()!=$teacherSchedule->getWednesday()  and $oSchedule->getThursday()!=$teacherSchedule->getThursday()  and $oSchedule->getFriday()!=$teacherSchedule->getFriday()) return true;
			return false;
		}		
	}
	//Return true if the teacher is vailable and false else
	protected function isAvailableBis($idmployee,$oContract){
		$oEmployee = $this->getEmployeeById($idemployee);
		$em = $this->getDoctrine()->getManager();
		$aTraining = $em->getRepository('BoAdminBundle:Training')->findBy(array('idcontracts'=>$oContract->getId()));
		$aSchedule = $this->getCurrentScheduleBy($oEmployee);
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getStartdate()>$teacherSchedule->getEnddate() or $teacherSchedule->getStartdate()>$oSchedule->getEnddate()) return true;
			elseif(($oSchedule->getStartam()>$teacherSchedule->getEndam() or $teacherSchedule->getStartam()>$oSchedule->getEndam()) and ($oSchedule->getStartpm()>$teacherSchedule->getEndpm() or $teacherSchedule->getStartpm()>$oSchedule->getEndpm())) return true;
			elseif($oSchedule->getMonday()!=$teacherSchedule->getMonday() and $oSchedule->getTuesday()!=$teacherSchedule->getTuesday()  and $oSchedule->getWednesday()!=$teacherSchedule->getWednesday()  and $oSchedule->getThursday()!=$teacherSchedule->getThursday()  and $oSchedule->getFriday()!=$teacherSchedule->getFriday()) return true;
			return false;
		}		
	}	
	protected function getActiveDay($oEntity){	
		return strval($oEntity->getMonday()).strval($oEntity->getTuesday()).strval($oEntity->getWednesday()).strval($oEntity->getThursday()).strval($oEntity->getFriday());
	}
	protected function isActiveStudent($oStudent){
		$aContracts = $this->getStudentContract($oStudent);
		return isset($aContracts[0])?1:0;
	}
	//get current and upcomming contracts
	protected function getCurrentStudentContract($oStudent){
		$today = new \DateTime(date("d-m-Y"));
		$aContracts = $oStudent->getContracts();
		foreach($aContracts as $oContract){
			if($oContract->getStatus()==1 and ($oContract->getStartdate()<=$today and $today<=$oContract->getEnddate())){
				return $oContract;
			}
		}
		return null;		
	}
	protected function getStudentContract($oStudent){
		$aResult = array();
		$today = new \DateTime(date("d-m-Y"));
		$aContracts = $oStudent->getContracts();
		foreach($aContracts as $oContract){
			if(($oContract->getStatus()==1 or $oContract->getStatus()==2) and (($oContract->getStartdate()<=$today and $today<=$oContract->getEnddate()) or $today<=$oContract->getStartdate())){
				$aResult[] = $oContract;
			}
		}
		return	$aResult;
	}
	/* Get student contract by date
	 * $oDate : given date 
	 * return array of contract object
	*/
	protected function getStudentContractByDate($oStudent,$oDate){
		$aResult = array();
		$aContracts = $oStudent->getContracts();
		foreach($aContracts as $oContract){
			if($oContract->getStartdate()<=$oDate and $oDate<=$oContract->getEnddate()){
				$aResult[] = $oContract;
			}
		}
		if(count($aResult)==1) return $aResult[0];
		return null;
	}
	function getAllStudentContract($oStudent){
		$oRepContract = $em->getRepository('BoAdminBundle:Contracts');
		$oGroup = $this->getStudentGroup($oStudent);
		$oRepContract = $em->getRepository('BoAdminBundle:Contracts');
		if($oGroup) return $oRepContract->getCurrentStudentGroup($student);
		return $oRepContract->getCurrentStudentContract($student);
	}
	protected function getAvailableTeachers($absences){
		$aResult = $astudent = $aAvailability =  array();
		foreach($absences as $oAbsence){
			foreach($oAbsence->getContracts() as $oContract){
				foreach($oContract->getEmployee() as $oEmployee){
					$availability = $this->getAvailByAbs($oAbsence,$oEmployee); 
					if($availability!=null){
						$aAvailability[$oEmployee->getId()]= $availability;
						$astudent[$oEmployee->getId()]= $this->getStudentByAbs($oAbsence,$oContract);
						$aResult[] = $oEmployee;
					}
				}
			}
			foreach($oAbsence->getGroup() as $oGroup){
				foreach($oGroup->getEmployee() as $oEmployee){
					$availability = $this->getAvailByAbs($oAbsence,$oEmployee); 
					if($availability!=null){
						$aAvailability[$oEmployee->getId()]= $availability;	
						$astudent[$oEmployee->getId()]= $oGroup->getName();
						$aResult[] = $oEmployee;
					}
				}
			}

		}
		return array($aResult,$aAvailability,$astudent);
	}
	protected function getAvailableTeachersFor($startdate,$enddate){
		$aTeachers = $this->getRepository('BoAdminBundle:Teachers')->getTeachersAvailableFor($startdate,$enddate);
		exit(0);
	}
	private function getAvailByAbs($oAbsence,$oEmployee){
		$today = $this->getToday();
		$aSubstitution = $this->getByHolderAndDate($oEmployee->getId(),$today);
		$aSubAvail = $this->getSubAvail($aSubstitution);
		if($oAbsence->getAmorpm() == self::PERIOD_DAY_ALL and !isset($aSubAvail['am']) and !isset($aSubAvail['pm'])){
                    return self::PERIOD_DAY_BOTH;
                }
		elseif($oAbsence->getAmorpm() == self::PERIOD_DAY_AM and !isset($aSubAvail['pm'])){
                    return self::PERIOD_DAY_PM;
                }
		elseif($oAbsence->getAmorpm() == self::PERIOD_DAY_PM and !isset($aSubAvail['am'])){
                    return self::PERIOD_DAY_AM;
                }
		return null;
	}
	private function getStudentByAbs($oAbsence,$oContract){
		$oGroup = $oContract->getGroup();
		if($oGroup) return $oGroup->getName();
		$oStudent = $oAbsence->getStudents();
		if($oStudent) return $this->getFullnameOf($oStudent);
		return null;
	}
	private function getSubAvail($aSubstitution){
		$aSubAvail = array();
		foreach($aSubstitution as $oSubstitution){
			$ham = $this->getRealAmHour($oSubstitution);
			if($ham>0) $aSubAvail['am']=$ham;
			$hpm = $this->getRealPmHour($oSubstitution);
			if($hpm>0) $aSubAvail['pm']=$hpm;
		}
		return $aSubAvail;
	}
	protected function getContractStudents($aContracts){
		$aResult = array();
		foreach($aContracts as $oContract){
			foreach($oContract->getStudents() as $oStudent){
				$aResult[]=$oStudent;
			}
		}
		return $aResult;		
	}
	//Get student Group
	protected function getStudentGroup($oStudent){
		return $this->getRepository('BoAdminBundle:Students')->getStudentGroup($oStudent);
	}
	//Get active contract
	protected function getStudentActiveContract($student){
		$now = new \DateTime(date("d-m-Y"));
		$aContracts = $student->getContracts();
		foreach($aContracts as $oContract){
			if($oContract->getStatus()==1 and ($oContract->getStartdate()<=$now and $oContract->getEnddate()>=$now)){
				return $oContract;
			}			
		}
		return null;
	}
	//Get active and upcoming contracts
	protected function getStudentBothContract($student){
		$now = new \DateTime(date('d-m-Y'));
		if($student==null) return $student;
		$aContracts = $student->getContracts();
		foreach($aContracts as $oContract){
			if($oContract->getEnddate()>=$now and ($oContract->getStatus()!=1 or $oContract->getStatus()!=2)){
				return $oContract;
			}			
		}
		return null;
	}
	//Get active contract with which have fields Id 1 and 2
	protected function getAllActiveContract(){
		$aContracts = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Contracts')->getAllActiveContract();
		return $aContracts;
	}
	//Get all active contract without any exception
	protected function getActiveContracts(){
		$aContracts = $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Contracts')->getActiveContracts();
		return $aContracts;
	}
	protected function getExistTsForContract($oContract,$oEmployee,$oDate){
		return $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Timesheet')->getExistTsForContract($oContract,$oEmployee,$oDate);
	}
	protected function getExistTsForGroup($oGroup,$oEmployee,$oDate){
		return $this->getDoctrine()->getManager()->getRepository('BoAdminBundle:Timesheet')->getExistTsForGroup($oGroup,$oEmployee,$oDate);
	}
	protected function setIsGroup($oContract){
		if($this->isGroupContract($oContract)==true) $oContract->setIsgroup(true);
		else $oContract->setIsgroup(false);
		return $oContract;
	}
	public function getContractBySchedule($aSchedule){
		$em = $this->getDoctrine()->getManager();
		$today = new \DateTime(date("d-m-Y"));
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getContracts()){
				$oContract = $oSchedule->getContracts();
				$oEmployee = $oSchedule->getEmployee();
				$aTS = ($oContract and $oEmployee)?$this->getExistTsForContract($oContract,$oEmployee,new \DateTime(date('d-m-Y'))):array();
				if($oContract and $this->isGroupContract($oContract)==false and count($aTS)==0){
					if(isset($aResult[$oContract->getId()])) continue;
					if($oContract->getStatus()==1 and $oContract->getStartdate()<=$today and $oContract->getEnddate()>=$today) $aResult[$oContract->getId()] = $oContract;
				}
			}
		}
		return $aResult;	 	
	}
	public function getContractByAgenda($aSchedule){
		$em = $this->getDoctrine()->getManager();
		$today = new \DateTime(date("d-m-Y"));
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getContracts()){
				$oContract = $oSchedule->getContracts();
				if($oContract and $this->isGroupContract($oContract)==false){
					if(isset($aResult[$oContract->getId()])) continue;
					if(($oContract->getStatus()==1 and $oContract->getStartdate()<=$today and $oContract->getEnddate()>=$today) or ($oContract->getStartdate()>=$today)) $aResult[$oContract->getId()] = $oContract;
				}
			}
		}
		return $aResult;	 	
	}
	public function getGroupByAgenda($aSchedule){
		$today = new \DateTime(date("d-m-Y"));
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getGroup()){
				$oGroup = $oSchedule->getGroup();
				$oEmployee = $oSchedule->getEmployee();
				if($oGroup){
					if(isset($aResult[$oGroup->getId()])) continue;
					if($oGroup->getStatus()==1 and $oGroup->getStartdate()<=$today and $oGroup->getEnddate()>=$today) $aResult[$oGroup->getId()] = $oGroup;
				}
			}
		}
		return $aResult;	 	
	}
	public function getContractsBySchedule($oSchedule){
		return $oSchedule->getContracts();
	}
	public function getGroupByScheduleBis($oSchedule){
		return $oSchedule->getGroup();
	}
	//Get as parameter an array
	public function getGroupBySchedule($aSchedule){
		$em = $this->getDoctrine()->getManager();
		$today = new \DateTime(date("d-m-Y"));
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getGroup()!=null){
				$oGroup = $oSchedule->getGroup();
				$oEmployee = $oSchedule->getEmployee();
				$aTS = ($oGroup and $oEmployee)?$this->getExistTsForGroup($oGroup,$oEmployee,new \DateTime(date('d-m-Y'))):array();
				if($oGroup and count($aTS)==0){
					if($oGroup->getStatus()==1 and $oGroup->getStartdate()<=$today and $oGroup->getEnddate()>=$today) $aResult[$oGroup->getId()] = $oGroup;
				}
			}
		}
		return $aResult;	 	
	}
	public function getEmployeeBySchedule($aSchedule){
		$em = $this->getDoctrine()->getManager();
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getIdemployee()>0){
				$oEmployee = $em->getRepository('BoAdminBundle:Employee')->find($oSchedule->getIdemployee());
				if(isset($aResult[$oEmployee->getId()])) continue;
				$aResult[$oEmployee->getId()] = $oEmployee;
			}
		}
		return $aResult;	 	
	}
	public function getEmployeeByScheduleBis($oSchedule){
		return $this->getEmployeeById($oSchedule->getIdemployee());
	}
	public function generateTsForAllContract($aContract){
		set_time_limit(0);
		foreach($aContract as $oContract){
			$aStudent = $oContract->getStudents();
			$aEmployee = $oContract->getEmployee();
			foreach($aEmployee as $oEmployee){
				$aSchedule = $this->getEmployeeContSchedule($oEmployee);
				$aContractIds = $this->overlapofSchedule($aSchedule);
				//Do not take into account all contract wich are overlaped
				if(in_array($oContract->getId(),$aContractIds)) continue;
				//get the schedule for the contract and the teacher
				$aTSc = $this->getExistTsForContract($oContract,$oEmployee,new \DateTime(date('d-m-Y')));
				if(count($aTSc)==0){
					$aAttendance = $this->getAttendanceBy($oContract,$oEmployee);
					$legende = $this->getHighLegendeBy($oContract);				
					$timesheet = new Timesheet();
					$timesheet->setDdate(new \DateTime());
					$timesheet = $this->generateTs($timesheet,$oEmployee,$oContract,$legende);
					$res = $this->updateEntity($timesheet);
					if($res>0){
						$this->createTsStudent($timesheet,$aAttendance);
						if(!$this->getExistPP($timesheet)) $oPP = $this->createPP($timesheet);
						//Create validation historic in the table TsValidation
						$this->createTsValidation($timesheet,"Payroll");
						//Create payroll data
						$this->manageTsHours($timesheet);
						//Create billing data
						$this->createBilling($timesheet);
						$aIdTs[]=$res;
					}
				}
			}
		}
		return $aIdTs;
	}
	public function overlapofSchedule($aSchedule){
		$aResult = array();
		foreach($aSchedule as $oSchedule1){
			foreach($aSchedule as $oSchedule2){
				if($oSchedule1->getContract()==null and $oSchedule2->getContract()) continue;
				if($oSchedule1->getContract()->getId()==$oSchedule2->getContract()->getId()) continue;
				if($this->isOverlap($oSchedule1,$oSchedule2)==true and !isset($aResult[$oSchedule1->getContract()->getId()])) $aResult[$oSchedule1->getContract()->getId()]=$oSchedule1->getContract()->getId();
			}
		}
		return $aResult;
	}
	public function overlapofScheduleGroup($aSchedule){
		$aResult = array();
		foreach($aSchedule as $oSchedule1){
			foreach($aSchedule as $oSchedule2){
				if($oSchedule1->getGroup()==null and $oSchedule2->getGroup()) continue;
				if($oSchedule1->getGroup()->getId()==$oSchedule2->getGroup()->getId()) continue;
				if($this->isOverlap($oSchedule1,$oSchedule2)==true and !isset($aResult[$oSchedule1->getGroup()->getId()])) $aResult[$oSchedule1->getGroup()->getId()]=$oSchedule1->getGroup()->getId();
			}
		}
		return $aResult;
	}
	//Check if there exist a substitution for this employee
//en cours 28/12/2017
	protected function overlapForSubs($oEmployee,$oSchedule,$option=null){
		$pmoram = $option==null?$oSchedule->getAmorpm():$this->getAmOrPmBy($oSchedule);
		$aSubstitution = $this->getRepository('BoAdminBundle:Substitution')->searchBySubstitute($oEmployee->getId(),$oSchedule->getStartdate(),$oSchedule->getEnddate());
		foreach($aSubstitution as $oSubstitution){

		}
	}

	public function overlapForEmployee($oSchedule,$oEmployee){
		$aResult = array();
		$aSchedule=$this->getAgendaByDates($oEmployee,$oSchedule->getStartdate(),$oSchedule->getEnddate());
		if(count($aSchedule)==0){
			$aSchedule=$this->getAgendaByEmployee($oEmployee);
		}
		foreach($aSchedule as $oSchedule1){
			if($this->isOverlap($oSchedule1,$oSchedule)==true){
				$aResult[$oSchedule1->getId()]=array('idcontrat'=>$oSchedule1->getContracts()?$oSchedule1->getContracts()->getId():null,'idgroup'=>$oSchedule1->getGroup()?$oSchedule1->getGroup()->getId():null);
			} 
		}
		return $aResult;
	}
	//Check if there are overlap on the two schedule
	//return true if there is overlap on the schedule else false
	public function isOverlap($oSchedule1,$oSchedule2){
		//this employee is holder of a contract and is substituted by another teacher
		$aSubsHolder = $this->getBySchedules($oSchedule1,$oSchedule2);
		//If there exist a susbtitution on the $oSchedule1
		if(count($aSubsHolder)>0) return false; 
		if($this->overlapDate($oSchedule1,$oSchedule2)==false) return false;
		if($this->overlapTime($oSchedule1,$oSchedule2)==false)	return false;
		if($this->overlapDay($oSchedule1,$oSchedule2)==false) return false;
		return true;			 
	}
	//return true if there is overlap and false else
	private function overlapDate($oSchedule1,$oSchedule2){
		if($oSchedule1->getEnddate()<$oSchedule2->getStartdate() or $oSchedule2->getEnddate()<$oSchedule1->getStartdate()) return false;
		return true;
	}
	//Si le endam du schedule 1 finit avant le startam du schedule 2 ou bien le startam du schedule 1 commence apres le endam du  schedule 2 (endam2<startam ) alors il n'y a pas de chevauchelent en AM, dans tous les autre cas il y a de chevauchement 
	private function overlapAm($oSchedule1,$oSchedule2){
		$startam1 = $oSchedule1->getStartam()!=null?$oSchedule1->getStartam():$this->getTime();
		$startam2 = $oSchedule2->getStartam()!=null?$oSchedule2->getStartam():$this->getTime();
		$Endam1 = $oSchedule1->getEndam()!=null?$oSchedule1->getEndam():$this->getTime();
		$Endam2 = $oSchedule2->getEndam()!=null?$oSchedule2->getEndam():$this->getTime();
		if($this->getRealHour($Endam1->format("H:i"))==0 and $this->getRealHour($startam2->format("H:i"))==0 and  $this->getRealHour($Endam2->format("H:i"))==0 and $this->getRealHour($startam1->format("H:i"))==0) return false;
		//	
		if($this->getRealHour($Endam1->format("H:i"))<=$this->getRealHour($startam2->format("H:i")) or $this->getRealHour($Endam2->format("H:i"))<=$this->getRealHour($startam1->format("H:i"))) return false;	
		else return true;	
	}

	//Si le endpm du schedule 1 finit avant le startpm du schedule 2 ou bien le startpm du schedule 1 commence apres le endpm du  schedule 2 (endpm2<startpm ) alors il n'y a pas de chevauchelent en PM, dans tous les autre cas il y a de chevauchement 
	private function overlapPm($oSchedule1,$oSchedule2){
		$startpm1 = $oSchedule1->getStartpm()!=null?$oSchedule1->getStartpm():$this->getTime();
		$startpm2 = $oSchedule2->getStartpm()!=null?$oSchedule2->getStartpm():$this->getTime();
		$Endpm1 = $oSchedule1->getEndpm()!=null?$oSchedule1->getEndpm():$this->getTime();
		$Endpm2 = $oSchedule2->getEndpm()!=null?$oSchedule2->getEndpm():$this->getTime();
		if($this->getRealHour($Endpm1->format("H:i"))==0 and $this->getRealHour($startpm2->format("H:i"))==0 and  $this->getRealHour($Endpm2->format("H:i"))==0 and $this->getRealHour($startpm1->format("H:i"))==0) return false;
		if($this->getRealHour($Endpm1->format("H:i"))<=$this->getRealHour($startpm2->format("H:i")) or $this->getRealHour($Endpm2->format("H:i"))<=$this->getRealHour($startpm1->format("H:i"))) return false;	
		else return true;	
	}
	//return true if there is overlap and false else
	private function overlapTime($oSchedule1,$oSchedule2){
		if($this->overlapAm($oSchedule1,$oSchedule2)==false and $this->overlapPm($oSchedule1,$oSchedule2)==false) return false;
		return true;
	}
	//return true if there is overlap and false else
	private function overlapDay($oSchedule1,$oSchedule2){
		if(((intval($oSchedule1->getMonday())==0 and intval($oSchedule2->getMonday())==0) or $oSchedule1->getMonday()!=$oSchedule2->getMonday()) and ((intval($oSchedule1->getTuesday())==0 and intval($oSchedule2->getTuesday())==0) or $oSchedule1->getTuesday()!=$oSchedule2->getTuesday()) and ((intval($oSchedule1->getWednesday())==0 and intval($oSchedule2->getWednesday())==0) or $oSchedule1->getWednesday()!=$oSchedule2->getWednesday()) and ((intval($oSchedule1->getThursday())==0 and intval($oSchedule2->getThursday())==0) or $oSchedule1->getThursday()!=$oSchedule2->getThursday()) and ((intval($oSchedule1->getFriday())==0 and intval($oSchedule2->getFriday())==0) or $oSchedule1->getFriday()!=$oSchedule2->getFriday())) return false;
		return true;
	}
	private function getBySchedules($oAgenda,$oSchedule){
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		return $oRepSubs->getBySchedules($oAgenda,$oSchedule);
	}
	//Check if the teacher is available to get the contract
	protected function checkAvailability($oSchedule,$oEmployee){
		$mesgroup=$mescont=null;
		$Fullname = $this->getFullNameOf($oEmployee);
		$message = $Fullname." is not available."." Check the schedule entered which is overlaped with ";
		//get schedule overlap, if equal to 0 then the teacher is available
		$aOverlap = $this->overlapForEmployee($oSchedule,$oEmployee);
		if(count($aOverlap)==0) return 1;
		$bAbs = $this->getStudentAbsence($oEmployee,$oSchedule);
		if($bAbs==1){
			return $bAbs; 
		}elseif($bAbs==2){
			$this->setSessionMessage("Warning","There is overlap on the schedules. Check if the end date scheduled does not exceed the teacher's avaibility.");
			return 0;
		}
		foreach($aOverlap as $id=>$aTab){
			$oOSchedule = $this->getRepository("BoAdminBundle:Agenda")->find($id);
			if($aTab['idgroup']>0){
				$oGroup = $this->getGroupById($aTab['idgroup']);
				$aGoup[] = $oGroup->getName();  
			}elseif($aTab['idcontrat']>0){
				$oContrat = $this->getContractById($aTab['idcontrat']);
				$aStudent = $oContrat->getStudents();
				if($oContrat->getGroup()!=null) $aGoup[] = $oContrat->getGroup()->getName(); 
				elseif(count($aStudent)==1){
					$aStudent = $oContrat->getStudents();
					$aContrat[] = "id ".$oContrat->getId()." de ".$aStudent[0]->getFirstname()." ".$aStudent[0]->getName(); 
				} 
			}elseif($oOSchedule->getDescription()!=null){
				$message = $message." another one with id ".$id." and the description : ".$oOSchedule->getDescription();	
			}	
		}
		if(isset($aGoup)){
			$mesgroup = "The group(s) ".join(',',$aGoup)." ";
		}
		if(isset($aContrat)){
			$mescont = "contract(s) ".join(',',$aContrat)." ";
		}
		if($mesgroup!=null and $mescont!=null) $message = $message.$mesgroup." and the ".$mescont;
		elseif($mesgroup!=null)	$message = $message.$mesgroup;
		elseif($mescont!=null)	$message = $message.$mescont;	
		$this->setSessionMessage("Warning",$message);
		return 0;		
	}
	protected function getStudentByContractId($idcontrat){
		$oContrat = $this->getContractById($idcontrat);
		return $oContrat->getStudents();
	}
	protected function getStudentByGroupId($idgroup){
		$oGroup = $this->getGroupById($idgroup);
		return $oGroup->getStudents();
	}
	private function getStudentAbsence($oEmployee,$oNewSchedule){
		$aSchedule=$this->getAgendaByEmployee($oEmployee);
		foreach($aSchedule as $oSchedule){
			$oContract = $this->getContractByoSchedule($oSchedule);
			if($oContract){
				$aStudent = $oContract->getStudents();
				if(count($aStudent)==1){
					$oStudent = $aStudent[0];
					$aAbsences = $this->getStudentAbsByDates($oStudent,$oNewSchedule->getStartdate(),$oNewSchedule->getEnddate());
					if(count($aAbsences)==0) $aAbsences = $this->getStudentAbsBySchedule($oStudent,$oNewSchedule);
					//Verify if exist an absence for the student
					if(isset($aAbsences[0]) and $oAbsence=$aAbsences[0]){
						$this->setSessionByName('absence',$oAbsence);
						if($this->IsDateBetweeen($oAbsence,$oNewSchedule,$oSchedule)==false){
						 	return 2;
						}
						if($this->existEntity($oAbsence,$oSchedule->getAbsences())==false){
							$oSchedule->addAbsence($oAbsence);
							$this->updateEntity($oSchedule);
						}
						return 1;
					}
				} 
			}
		
		}
		return 0;
	}
	private function IsDateBetweeen($oAbsence,$oNewSchedule,$oSchedule){
		//If there is not overlap on the two schedule then 
		if($this->isOverlap($oNewSchedule,$oSchedule)==false) return true;
		//get all date overlap
		$aDates = $this->getDateOverlap($oNewSchedule,$oSchedule);
		if(!isset($aDates[0])) return true;
		foreach($aDates as $amorpm=>$oDate){
			if($amorpm=="AM & PM") $amorpm="ALL"; 
			if($oAbsence->getStartdate()>$oDate or $oDate>$oAbsence->getEnddate()) return false;
			if($oAbsence->getAmorpm()!='ALL') return false;
		}
		return true;
	}
	protected function getContractByoSchedule($oSchedule){
		return $oSchedule->getContracts();
	}
	protected function getGroupByoSchedule($oSchedule){
		return $oSchedule->getGroup();
	}
	protected function getArrayContractBy($aSchedule){
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			$oContract = $oSchedule->getContracts();
			if($oContract and $oContract->getGroup()==null) $aResult[$oContract->getId()] =  $oContract;
		}
		return $aResult;
	}
	protected function getArrayGroupBy($aSchedule){
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			$oGroup = $oSchedule->getGroup();
			if($oGroup) $aResult[$oGroup->getId()] =  $oGroup;
		}
		return $aResult;
	}
	protected function getGroupById($id){
		$em = $this->getDoctrine()->getManager();
		return $em->getRepository('BoAdminBundle:Group')->find($id);		
	}
	protected function getContractById($id){
		if($id==null or $id<=0)
		$em = $this->getDoctrine()->getManager();
		return $this->getRepository('BoAdminBundle:Contracts')->find($id);		
	}
	private function getDateOverlap($oNewSchedule,$oSchedule){
		$aResult = array();
		$start = $oNewSchedule->getStartdate();
		$end = $oNewSchedule->getEnddate();
		while($start<=$end){
			$existDay = $this->existDaySchedule($oSchedule,$start);
			if($existDay==1) $aResult[$oSchedule->getAmorpm()] = $start;
			$start = $this->getDatePlus($start,1); 
		}
		return $aResult;
	}
	public function generateTsForAllGroup($aGroup){
		set_time_limit(0);
		foreach($aGroup as $oGroup){
			$aStudent = $oGroup->getStudents();
			if(count($aStudent)==0) $aStudent = $this->getStudentByGroup($oGroup);
			$aEmployee = $oGroup->getEmployee();
			foreach($aEmployee as $oEmployee){
				$aSchedule = $this->getEmployeeContSchedule($oEmployee);
				$aGroupIds = $this->overlapofScheduleGroup($aSchedule);
				//Do not take into account all contract wich are overlaped
				if(in_array($oGroup->getId(),$aGroupIds)) continue;
				//get the schedule for the contract and the teacher
				$aTS = $this->getExistTsForGroup($oGroup,$oEmployee,new \DateTime(date('d-m-Y')));
				if(count($aTS)==0){
					//Get student presence for the group
					$aAttendance = $this->getAttendanceBy($oGroup,$oEmployee);
					$legende = $this->getHighLegendeBy($oGroup,1);	
					$timesheet = new Timesheet();
					$timesheet->setDdate(new \DateTime());
					$timesheet = $this->generateTsForGroup($timesheet,$oEmployee,$oGroup,$legende);
					$res = $this->updateEntity($timesheet);
					if($res>0){
						$this->createTsStudent($timesheet,$aAttendance);
						if(!$this->getExistPP($timesheet)) $oPP = $this->createPP($timesheet);
						//Create validation historic in the table TsValidation
						$this->createTsValidation($timesheet,"Payroll");
						//Create payroll data
						$this->manageTsHours($timesheet);
						//Create billing data
						$this->createBilling($timesheet);
						$aIdTs[]=$res;
					}
				}
			}
		}
		return $aIdTs;
	}
	public function getStudentByGroup($oGroup){
		$aResult = array();
		$aContract = $this->getContractByGroup($oGroup);
		foreach($aContract as $oContract){
			$aStudent = $oContract->getStudents();
			foreach($aStudent as $oStudent){
				$aResult[] = $oStudent;
			}
		}
		return $aResult;
	}
	//Get student precence for contract or for group
	//oEntity can be contract or group. if option is null then it's for a contract else it's for a group
	protected function getAttendanceBy($oEntity,$oEmployee,$option=null){
		$aStudents = $oEntity->getStudents();
		$today = new \DateTime(date("d-m-Y"));
		$legend = "P";
		$am = $pm = null;
		$ham = $hpm = null;
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:Absences');
		$aLegende=array();
		foreach($aStudents as $oStudent){
			$aAbsence = $oRepAbs->getStudentsAbsencesByDate($oStudent,$today);
			//Check if there exists a substitution for this contract and for this employee and for this date
			if($option==null){
				$aSubstitution = $this->getTodaySubstitutionBy($oEntity,$oEmployee);
				$aSchedule = count($aSubstitution)>0?$aSubstitution:$this->getContractTeacherSchedule($oEmployee,$oEntity);
			}else{
				$aSubstitution = $this->getTodayGroupSubstitutionBy($oEntity,$oEmployee);
				$aSchedule = count($aSubstitution)>0?$aSubstitution:$this->getGroupTeacherSchedule($oEmployee,$oEntity);				
			}
			if(isset($aAbsence[0])){
				$aLegend = $this->getAbsLegends($aAbsence[0]);
				if(isset($aLegend[$today->format("d-m-Y")])) $legend = $aLegend[$today->format("d-m-Y")];
			}
			if(isset($aSchedule[0]) and $oSchedule=$aSchedule[0]){
				$amhour = $this->getRealAmHour($oSchedule);	
				$pmhour = $this->getRealPmHour($oSchedule);
				if($amhour>0){
					$am = $legend;
					$ham = $amhour; 
				}
				if($pmhour>0){
					$pm = $legend;
					$hpm = $pmhour; 
				}				
			}
			$aLegende[]=array('student'=>$oStudent,'legende'=>$legend,'delay'=>null,'dh'=>null,'am'=>$am,'pm'=>$pm,'ham'=>$ham,'hpm'=>$hpm);
		}		
		return $aLegende;
	}
	protected function getHighLegendeBy($oEntity,$option=null){
		$em = $this->getDoctrine()->getManager();
		$oRepAbs = $em->getRepository('BoAdminBundle:Absences');
		if($option!=null) $aStudents = $this->getStudentByGroup($oEntity);
		else $aStudents = $oEntity->getStudents();
		$today = new \DateTime(date("d-m-Y"));
		$aResult=array();
		foreach($aStudents as $oStudent){
			$aAbsence = $oRepAbs->getStudentsAbsencesByDate($oStudent,$today);
			if(isset($aAbsence[0]) and $oAbsence = $aAbsence[0]){					
				$aLegend = $this->getAbsLegends($oAbsence);
				if(isset($aLegend[$today->format("d-m-Y")])) $legend = $aLegend[$today->format("d-m-Y")];
			}else $legend = "P";
			$aResult[$legend] = $legend;
		}	
		return $this->getBigLegende($aResult);
	}
	protected function getHighPresenceBy($oEntity,$tam,$tpm,$option=null){
		$em = $this->getDoctrine()->getManager();
		$legend = 'P';
		if($option!=null) $aStudents = $this->getStudentByGroup($oEntity);
		else $aStudents = $oEntity->getStudents();
		$aPresence = $this->getPresence($aStudents);
		if(count($aStudents)==1) return $this->getHighByPresence($aPresence);
		foreach($aPresence as $key=>$aTab){ 
			if(($tam>0 and $tpm>0) or $tam>0) $aResult[$aTab['am']] = $aTab['am'];
			elseif($tpm>0) $aResult[$aTab['pm']] = $aTab['pm'];
			else $aResult[$legend] = $aTab[$legend];
		}	
		return $this->getBigLegende($aResult);
	}
	protected function getHighByPresence($aPresence){
		$aResult = array();
		foreach($aPresence as $aTab){
			foreach($aTab as $string){
				$aResult[$string] = $string;
			}
		}
		return $this->getBigLegende($aResult);
	}
	protected function getHighByStudent($aStudents){
		$aPresence=$this->getPresence($aStudents);
		return $this->getHighByPresence($aPresence);

	}
	protected function getArrayIds($aEntities){
		$aIds = array();
		foreach($aEntities as $oEntity){
			$aIds[]=$oEntity->getId();
		}
		return $aIds;
	}
	protected function getStringIds($aEntities){
		$aIds = $this->getArrayIds($aEntities);
		return join(",",$aIds);
	}
	//Return true if there exists timesheet for the employee
	protected function checkExistingTsFor($oEmployee,$oDate=null){
		if($oDate==null) $oDate = $this->getToday();
		$oRep = $this->getRepository('BoAdminBundle:Timesheet');
		$aTs = $oRep->getDayTsByEmployee($oEmployee,$oDate);
		$aSchedule = $this->getScheduleByDate($oEmployee,$oDate);
		$fTotalTs = $this->getTotalTsHour($aTs);
		$fTotalSch = $this->getTotalSchedule($aSchedule);
		if($fTotalTs == $fTotalSch) return true;
		else return false;
	}
	protected function getTotalTsHour($aTimesheet){
		$hour = 0;
		foreach($aTimesheet as $oTimesheet){
			$hour = $hour+$oTimesheet->getHour();
		}
		return $hour;
	}	
	protected function getTotalSchedule($aSchedule){
		$hour = 0;
		foreach($aSchedule as $oSchedule){
			$hour = $hour+$oSchedule->getHourperday();
		}
		return $hour;
	}
	protected function getCreatingTsHour(){
		//getting the parameter of timesheet creation if 0 timesheet can be created before the end of course else after 
		$pcth = $this->getParam("timesheet_hour_override",24);
		if($pcth==0) return $pcth;
		//cth is the time when the timesheet is authorized to be created
		//add $pcth to hour of moment
		$cth = $this->getRealHour(date("H:i"))+$pcth;
		return $cth;
	}
	protected function getDtsb($aTab,$option){
		$aResult = array();
		if(count($aTab)==0) return $aResult;
		//getting the parameter of timesheet creation if 0 timesheet can be created before the end of course else after 
		$pcth = $this->getParam("timesheet_hour_override",24);
		$cth = $this->getRealHour(date("H:i"))+$pcth;
		foreach($aTab as $oEntity){
			if($option!=1 and $oEntity->getGroup()) $id = $oEntity->getGroup()->getId();
			elseif($oEntity->getContracts()) $id = $oEntity->getContracts()->getId();
			if($pcth==0) $aResult[$id]=1;
			else{
				$ham = $this->getRealAmHour($oEntity);
				$hpm = $this->getRealPmHour($oEntity);
				if(($ham>0 and $hpm>0) or $hpm>0){
					$endpm = $this->getRealHour($oEntity->getEndpm()->format("H:i"));
					$aResult[$id]=$cth>$endpm?1:0;

				}elseif($ham>0){
					$endam = $this->getRealHour($oEntity->getEndam()->format("H:i"));
					$aResult[$id]=$cth>$endam?1:0;
				}

			}
		}
		return $aResult;
		
	}
	protected function getDtsbForSub($aTab){
		$aResult = array();
		if(count($aTab)==0) return $aResult;
		//getting the parameter of timesheet creation if 0 timesheet can be created before the end of course else after 
		$pcth = $this->getParam("timesheet_hour_override",24);
		$cth = $this->getRealHour(date("H:i"))+$pcth;
		foreach($aTab as $oEntity){
			if($pcth==0) $aResult[$oEntity->getId()]=1;
			else{
				$ham = $this->getRealAmHour($oEntity);
				$hpm = $this->getRealPmHour($oEntity);
				if(($ham>0 and $hpm>0) or $hpm>0){
					$endpm = $this->getRealHour($oEntity->getEndpm()->format("H:i"));
					$aResult[$oEntity->getId()]=$cth>$endpm?1:0;

				}elseif($ham>0){
					$endam = $this->getRealHour($oEntity->getEndam()->format("H:i"));
					$aResult[$oEntity->getId()]=$cth>$endam?1:0;
				}

			}
		}
		return $aResult;
		
	}
	protected function getOneContractByGroup($oGroup){
		$aContract = $this->getContractByGroup($oGroup);
		if(count($aContract)>0) return $aContract[0];
		return null;
	}
	protected function getData($oContract,$oGroup,$oSchedule,$oEmployee){
		$aResult = array();
		if(!$oContract and $oGroup){
			$oContract = $this->getOneContractByGroup($oGroup);
			$aResult['group'] = $oGroup->getName();
			if($oGroup->getAdvisor()) $aResult['advisor'] = $this->getFullnameOf($oGroup->getAdvisor());
			$targetlevel = $oGroup->getTargetlevel();
			if($oGroup->getLocal()){
				$aLocal = $oGroup->getLocal();
				if(count($aLocal)>0) $aResult['room'] = $aLocal[0]->getReference();
			}
		}else{
			if($oContract->getGroup()) $aResult['group'] = $oContract->getGroup()->getName();
			else $aResult['student'] = $this->getStudentBy($oContract);
			if($oContract->getAdvisor()) $aResult['advisor'] = $this->getFullnameOf($oContract->getAdvisor());
			$targetlevel = $oContract->getTargetlevel();
			if($oContract->getLocal()){
				$aLocal = $oContract->getLocal();
				if(count($aLocal)>0) $aResult['room'] = $aLocal[0]->getReference();
			}
		}
		$aResult['language'] = $oContract->getLanguage();
		$aResult['startdate'] = $oSchedule->getStartdate();
		$aResult['enddate'] = $oSchedule->getEnddate();
		$schedule = $this->getRepository("BoAdminBundle:Agenda")->getScheduleDays($oSchedule);
		$aResult['schedule'] = $schedule;
		$aResult['targetlevel'] = $targetlevel;
		if($oContract->getCampus()) $aResult['location'] = $oContract->getCampus()->getAddress();
		if($oContract->getAdresse()) $aResult['adresse'] = $oContract->getAdresse();
		elseif($oContract->getCampus() and $oContract->getCampus()->getAdresse()) $aResult['location'] = $oContract->getLocation();
		return $aResult;

	}
	//METHODS OF AGENDA 
	protected function getAgendaByContract($oContract){
		$aResult = array();
		$aAgenda = $this->getRepository("BoAdminBundle:Agenda")->getByContract($oContract);
		foreach($aAgenda as $oAgenda){
			//update the status
			$oAgenda = $this->setAgendaStatus($oAgenda);
			$this->updateEntity($oAgenda);
			$aResult[] = $oAgenda;	
		}
		return $aResult;
	}
	protected function getActiveByContract($oContract){
		$aResult = array();
		$aAgenda = $this->getRepository("BoAdminBundle:Agenda")->getByContract($oContract);
		foreach($aAgenda as $oAgenda){
			//update the status
			$oAgenda = $this->setAgendaStatus($oAgenda);
			$this->updateEntity($oAgenda);
			if($oAgenda->getStatus()==1 or $oAgenda->getStatus()==2){ 
				$aResult[] = $oAgenda;
			}
		}
		return $aResult;
	}
	protected function getClosedByContract($oContract){
		$aResult = array();
		if($oContract==null) return $aResult;
		$aAgenda = $oContract->getAgenda();
		foreach($aAgenda as $oAgenda){
			if($oAgenda->getStatus()==0) $aResult[] = $oAgenda;	
		}
		return $aResult;
	}
	protected function getAgendaByGroup($oGroup){
		$aResult = array();
		$aAgenda = $this->getRepository("BoAdminBundle:Agenda")->getByGroup($oGroup);
		foreach($aAgenda as $oAgenda){
			//update the status
			$oAgenda = $this->setAgendaStatus($oAgenda);
			$this->updateEntity($oAgenda);
			$aResult[] = $oAgenda;	
		}
		return $aResult;
	}
	protected function getActiveByGroup($oGroup){
		$aResult = array();
		$aAgenda = $this->getRepository("BoAdminBundle:Agenda")->getByGroup($oGroup);
		foreach($aAgenda as $oAgenda){
			//update the status
			$oAgenda = $this->setAgendaStatus($oAgenda);
			$this->updateEntity($oAgenda);
			if($oAgenda->getStatus()==1 or $oAgenda->getStatus()==2)
			$aResult[] = $oAgenda;	
		}
		return $aResult;
	}
	protected function getClosedByGroup($oGroup){
		$aResult = array();
		if($oGroup==null) return $aResult;
		$aAgenda = $oGroup->getAgenda();
		foreach($aAgenda as $oAgenda){
			if($oAgenda->getStatus()==0) $aResult[] = $oAgenda;	
		}
		return $aResult;
	}
	protected function setAgendaStatus($oAgenda){
		$oContrat = $oAgenda->getContracts();
		$oGroup = $oAgenda->getGroup();
		if($oAgenda->getEnddate()<$this->getToday()){
			$oAgenda->setStatus(0);
		}elseif($oGroup and $oGroup->getStatus()==0){
			$oAgenda->setStatus(0);
			//if($this->getGroupClosedate($oGroup)!=null) $oAgenda->setEnddate($this->getGroupClosedate($oGroup));
			//else $oAgenda->setEnddate($oGroup->getEnddate());
		}elseif($oContrat and $oContrat->getStatus()==0){
			$oAgenda->setStatus(0);
			//if($oContrat->getCloseddate()) $oAgenda->setEnddate($oContrat->getCloseddate());
			//else $oAgenda->setEnddate($oContrat->getEnddate());
		}elseif($oAgenda->getStartdate()>$this->getToday()){
			$oAgenda->setStatus(2);
		}else{
			$oAgenda->setStatus(1);
		}
		return $oAgenda;
	}
	private function getGroupClosedate($oGroup){
		if(count($oGroup->getClosegroup())==0) return null;
		$closedate = null;
		foreach($oGroup->getClosegroup() as $oClosegroup){
			if($closedate == null or $oClosegroup->getCloseddate()>$closedate) $closedate=$oClosegroup->getCloseddate();
		}
		return $closedate;
	}
	protected function updateAgenda($oAgenda){
		$oAgenda = $this->setAgendaStatus($oAgenda);
		$this->updateEntity($oAgenda);
		return $oAgenda;
	}
	protected function updateAgendaStatus($oAgenda){
		$oAgenda = $this->setAgendaStatus($oAgenda);
		$this->updateEntity($oAgenda);
		return $oAgenda;
	}
	protected function getAgendaBy($oContract){
		if($this->isGroupContract($oContract)==true) return $this->getAgendaByGroup($oContract->getGroup());
		return $this->getAgendaByContract($oContract);
	}
	protected function getActiveBy($oContract){
		if($this->isGroupContract($oContract)==true) return $this->getActiveByGroup($oContract->getGroup());
		return $this->getActiveByContract($oContract);
	}
	protected function updateAgendaForContract($oContract){
		$aAgenda = $this->getActiveByContract($oContract);
		foreach($aAgenda as $oAgenda){
			$this->updateAgenda($oAgenda);
		}
		return true;
	}
	protected function updateAgendaAfterClosing($oContract,$oGroup=null){
		if($oGroup==null) $aAgenda = $this->getActiveByContract($oContract);
		else $aAgenda = $this->getActiveByGroup($oContract);
		foreach($aAgenda as $oAgenda){
			$oAgenda->setStatus(0);
			$oAgenda->setEnddate($this->getToday());
			$this->updateEntity($oAgenda);
		}
		return true;
	}
	protected function getClosedBy($oContract){
		if($this->isGroupContract($oContract)==true) return $this->getClosedByGroup($oContract->getGroup());
		return $this->getClosedByContract($oContract);
	}
	protected function getScheduleByEmployee($idemployee){
		$oEmployee = $this->getRepository("BoAdminBundle:Employee")->find($idemployee);
		return $this->getAgendaByEmployee($oEmployee);
	}
	/*
	* @param : $oEmployee employee's entiy and $option = null when we need to take the day date else date equal to date - 30 days
	* To get the employee contracts : individual or group
	*/
	protected function getAgendaByEmployee($oEmployee,$option=null){
		$aResult = array();
		$aSchedule = $oEmployee->getAgenda();	
		$today = $option==null?$this->getToday():$this->getDateMoins($this->getToday(),30);
		foreach($aSchedule as $oSchedule){
			if($this->isArchived($oSchedule->getContracts())==true) continue;
			if(($oSchedule->getStartdate()<=$today and $today<=$oSchedule->getEnddate()) or $oSchedule->getStartdate()>$today)
			$aResult[] = $oSchedule;
		}
		return $aResult;		
	}
	protected function getAgendaByDate($oEmployee,$oDate){
		return $this->getRepository("BoAdminBundle:Agenda")->getByEmployeeAndDate($oEmployee,$oDate);	
	}
	protected function getAgendaByDates($oEmployee,$oDate1,$oDate2){
		return $this->getRepository("BoAdminBundle:Agenda")->getByEmployeeAndDates($oEmployee,$oDate1,$oDate2);	
	}
	protected function disableThis($oAgenda){
		$oAgenda->setStatus(0);
		return $this->updateEntity($oAgenda);
	}	
	protected function removeTeacherFrom($oAgenda){
		$oContract = $oAgenda->getContracts();
		$oGroup = $oAgenda->getGroup();
		$oEmployee = $oAgenda->getEmployee();
		if($oContract and $this->existEntity($oEmployee,$oContract->getEmployee())==true){
	 		$oContract->removeEmployee($oEmployee);
			return $this->updateEntity($oContract);
		}
		if($oGroup and $this->existEntity($oEmployee,$oGroup->getEmployee())==true){
	 		$oGroup->removeEmployee($oEmployee);
			return $this->updateEntity($oGroup);
		}
		return null;
	}
	protected function cloneAgenda($oAgenda,$oSubstitution){
		$oNew_agenda = clone $oAgenda;
		$oNew_agenda->setStartdate($oSubstitution->getStartdate());
		$oNew_agenda->setEnddate($oSubstitution->getEnddate());
		$oNew_agenda->setStartam($oSubstitution->getStartam());
		$oNew_agenda->setEndam($oSubstitution->getEndam());
		$oNew_agenda->setStartpm($oSubstitution->getStartpm());
		$oNew_agenda->setEndpm($oSubstitution->getEndpm());
		$oNew_agenda->setHourperday($oSubstitution->getHour());
		$oNew_agenda->setMonday($oSubstitution->getMonday());
		$oNew_agenda->setTuesday($oSubstitution->getTuesday());
		$oNew_agenda->setWednesday($oSubstitution->getWednesday());
		$oNew_agenda->setThursday($oSubstitution->getThursday());
		$oNew_agenda->setFriday($oSubstitution->getFriday());
		return $oNew_agenda;
	}
	
	protected function getSubstitutions($oContract){
		$aResult = array();
		if($this->isGroupContract($oContract) and $oContract->getGroup()) $aSubstitution = $this->getGroupSubstitution($oContract->getGroup());
		else $aSubstitution = $this->getContractSubstitution($oContract);
		foreach($aSubstitution as $oSubstitution){
			if($this->isActiveSubstitution($oSubstitution)==true) $aResult[] = $oSubstitution;
		}
		return $aResult;
	}
	private function isActiveSubstitution($oSubstitution){
		$today = $this->getToday();
		if(($oSubstitution->getStartdate()<=$today and $today<=$oSubstitution->getEnddate()) or $oSubstitution->getStartdate()>=$today){
			return true;
		}
		return false;
	}
	protected function getSubTeachers($aSubstitution){
		$aResult = array();
		foreach($aSubstitution as $oSubstitution){
			if(!isset($aResult[$oSubstitution->getIdholder()])) $aResult[$oSubstitution->getIdholder()] = $this->getEmployeeById($oSubstitution->getIdholder());
			if(!isset($aResult[$oSubstitution->getIdsubstitute()])) $aResult[$oSubstitution->getIdsubstitute()] = $this->getEmployeeById($oSubstitution->getIdsubstitute());
		}
		return $aResult;
	}
	//Invitation teacher for a agenda schedule
	protected function createInvitation($category,$agenda){
		if($this->existInvitation($agenda,$category)==true){
			$aInvitation = $this->getExistInvitation($agenda,$category);
			return $aInvitation[0];
		}
		$oInvitation = new Invitation();
		$oInvitation->setCategory($category);
		$oInvitation->setCreatedby($this->getConnected());
		$oInvitation->setAgenda($agenda);
		$res = $this->updateEntity($oInvitation);
		if($res>0) return $oInvitation;
		return null;
	}
	protected function setStatusInvitation($oEmployee,$status){
		$aInvitation = $this->getRepository("BoAdminBundle:Invitation")->findBy(array('employee'=>$oEmployee));
		if(isset($aInvitation[0]) and $oInvitation=$aInvitation[0]){
			$oInvitation->setStatus($status);
			$this->updateEntity($oInvitation);
			return true;
		}
		return false;
	}
	protected function getStudentNameBy($oContract,$oGroup){
		if($oContract and $this->getStudentName($oContract)!=null) return $this->getStudentName($oContract);
		elseif($oGroup) return $oGroup->getName();
		return null;
	}
	private function existInvitation($agenda,$category){
		$today = date("Y-m-d");
		$aEInvitation = $this->getExistInvitation($agenda,$category);
		if(isset($aEInvitation[0])){
			$formatDate = $aEInvitation[0]->getCreationdate()->format("Y-m-d");
			if($today==$formatDate){
				return true;
			}
		}
		return false;
	}
	private function getExistInvitation($agenda,$category){
		return $this->getRepository("BoAdminBundle:Invitation")->getByDate($agenda,$category);
	}
	protected function getInvitationBy($oEmployee){
		return $this->getRepository("BoAdminBundle:Invitation")->getByEmployee($oEmployee);
	}
    /**
    * Send invitation for mail 
    */
    protected function sendInvitation($oSchedule){
	$res = $oContract = $oGroup = null;
	$cc = $this->getRepository('BoAdminBundle:Param')->getParam("email_client_service",9);
	if($oSchedule->getContracts()!=null) $oContract = $oSchedule->getContracts();
	if($oSchedule->getGroup()!=null) $oGroup = $oSchedule->getGroup();
	$oEmployee = $oSchedule->getEmployee();
	$to = $this->getEmailEmployee($oEmployee);
	if($oContract and $oContract->getAdvisor()) $advisor_email = $oContract->getAdvisor()->getEmail();
	elseif($oGroup and $oGroup->getAdvisor()) $advisor_email = $oGroup->getAdvisor()->getEmail();
	$subject = "Invitation pour un nouveau cours";
	if($to!=null){
		$aMailFooter = $this->getRepository('BoAdminBundle:Mails')->getInfoMail('invitation_footer',9);
		$aMailHead = $this->getRepository('BoAdminBundle:Mails')->getInfoMail('invitation_head',8);
		$oInvitation = $this->createInvitation(1,$oSchedule);
		if($oInvitation){
			$body = $this->renderView("BoAdminBundle:Agenda:invitation.html.twig", array('contract'=>$oContract,'group'=>$oGroup,'agenda'=>$oSchedule,'studentname'=>$this->getStudentNameBy($oContract,$oGroup),'mailhead'=>$aMailHead->getMessageen(),'employee'=>$oEmployee,'acceptlink'=>$this->getLink(1,$oInvitation->getId()),'rejectlink'=>$this->getLink(2,$oInvitation->getId())));
			$oInvitation->setContent($this->getContent($oContract,$oGroup,$oSchedule,$aMailHead,$oEmployee));
			$this->updateEntity($oInvitation);
			if (!in_array(@$_SERVER['REMOTE_ADDR'], array(
				'127.0.0.1',
				'::1',
			)))				
			$res = $this->sendmail($to,$subject,$body,$cc,"jnvekounou@yahoo.fr");
		}
	}
	return $res;		
      }
	protected function closeContract($oContract,$option=null){
		$oContract->setStatus(0);
		$oContract->setClosedby($this->getUserIdentity());
		if($option!=null) $oContract->setClosedby("The system");
		$oContract->setCloseddate(new \DateTime());
		$res = $this->updateEntity($oContract);
		if($res>0 and $option==null){ 
			$this->updateLocalAfterClosing($oContract);
			$this->updateAgendaAfterClosing($oContract);
		}
		return array($res,$oContract);
	}
	protected function closeGroupContract($oContract,$option=null){
		$oContract->setStatus(0);
		if($option==null) $oContract->setClosedby($this->getUserIdentity());
		else $oContract->setClosedby("The system");
		$oContract->setCloseddate(new \DateTime());
		$res = $this->updateEntity($oContract);
		return $res;
	}
	protected function reopenContract($oContract,$option=null){
		$oContract->setStatus(1);
		$oContract->setOpenedby($this->getUserIdentity());
		$oContract->setOpeneddate(new \DateTime());
		$res = $this->updateEntity($oContract);
		if($res>0 and $option==null){ 
			$this->updateLocalAfterClosing($oContract);
			$this->updateAgendaAfterClosing($oContract);
		}
		return array($res,$oContract);
	}
	protected function closeGroup($oGroup,$option=null){
		$this->closeAllContract($oGroup,$option);
		$oClosegroup = new Closegroup();
		if($option==null) $oClosegroup->setClosedby($this->getUserIdentity());
		else $oClosegroup->setClosedby("The system");
		$oClosegroup->setGroup($oGroup);
		$oClosegroup->setCloseddate(new \DateTime());
		$res = $this->updateEntity($oClosegroup);
		$oGroup->setStatus(0);
		$res = $this->updateEntity($oGroup);
		if($res>0){ 
			$this->updateLocalAfterClosing($oGroup);
			$this->updateAgendaAfterClosing(null,$oGroup);
		}
		return array($res,$oGroup);
	} 
	protected function reopenGroup($oGroup){
		$this->reopenAllContract($oGroup);
		$aClosegroup = $this->getRepository('BoAdminBundle:Closegroup')->getNotOpened($oGroup);
		if(isset($aClosegroup[0])){
			$oClosegroup=$aClosegroup[0];
			$oClosegroup->setReopenby($this->getUserIdentity());
			$oClosegroup->setReopendate(new \DateTime());
			$res = $this->updateEntity($oClosegroup);
		}
		$oGroup->setStatus(1);
		$res = $this->updateEntity($oGroup);
		if($res>0){ 
			$this->updateLocalAfterClosing($oGroup);
			$this->updateAgendaAfterClosing(null,$oGroup);
		}
		return array($res,$oGroup);
	} 
	protected function closeAllContract($oGroup,$option=null){
		$aContracts = $this->getRepository('BoAdminBundle:Contracts')->findByGroup($oGroup);
		foreach($aContracts as $oContract){			
			$this->closeGroupContract($oContract,$option);						
		}
		return true;
	} 
	protected function reopenAllContract($oGroup){
		$aContracts = $this->getRepository('BoAdminBundle:Contracts')->findByGroup($oGroup);
		foreach($aContracts as $oContract){
			$this->reopenContract($oContract,1);						
		}
		return true;
	} 
	protected function getNotOpened($oGroup){

	}
	protected function setNullAm($oEntity){
		$oEntity->setStartam($this->getTime());
		$oEntity->setEndam($this->getTime());
		return $oEntity;
	}
	protected function setNullPm($oEntity){
		$oEntity->setStartpm($this->getTime());
		$oEntity->setEndpm($this->getTime());
		return $oEntity;
	}
	protected function checkSchedule($oEntity){
		//if the start time of the PM is lower than 12:00 send message to view
		if($oEntity and $oEntity->getStartpm()) $startpm = $this->getRealTime($oEntity->getStartpm()->format("H:i"));
		else $startpm=0; 
		if($startpm>0 and $startpm<12) return 2;
		//if the end time of the AM is higher than 12:00 send message to view
		if($oEntity and $oEntity->getEndam()) $endam = $this->getRealTime($oEntity->getEndam()->format("H:i"));
		else $endam = 0;
		//if($endam>12) return 2;

		if($oEntity->getStartam()>$oEntity->getEndam() or $oEntity->getStartpm()>$oEntity->getEndpm()) return 0;
		if($oEntity->getStartam()!=$this->getTime() and $oEntity->getEndam()!=$this->getTime()){
			if($oEntity->getStartam()==$oEntity->getEndam()){
				$oEntity->setStartam($this->getTime());
				$oEntity->setEndam($this->getTime());
			}
		}

		if($oEntity->getStartpm()!=$this->getTime() and $oEntity->getEndpm()!=$this->getTime()){
			if($oEntity->getStartpm()==$oEntity->getEndpm()){
				$oEntity->setStartpm($this->getTime());
				$oEntity->setEndpm($this->getTime());
			}
		}
		$Realhour = $this->getRealHourPerDay($oEntity);	
		if($Realhour==$oEntity->getHourperday()) return 1;
		return 0;
	}
	public function getStudentDaySchedule($oStudent,$oDate,$option,$oContract=null){
		$oRep = $this->getRepository("BoAdminBundle:Agenda");
		//Verify if the student belong to a group. If it's true then get schedule of the group
		$oGroup = $this->getStudentGroup($oStudent);
		if($oGroup){
			$aSchedule = $oRep->getScheduleByGroup($oGroup,$oDate);
			return $this->getScheduleByDay($aSchedule,$oDate,$option);
		}			
		if($oContract==null) $oContract = $this->getStudentContractByDate($oStudent,$oDate);
		//If the student is not belong to a group then get schedule of student by his contract
		if($oContract!=null){
			$aSchedule = $this->getScheduleByContract($oContract,$oDate);
			//Return the result
			return $this->getScheduleByDay($aSchedule,$oDate,$option);
		}
		return array();
	}
	public function getScheduleByContract($oContract,$oDate){
		$oRep = $this->getRepository("BoAdminBundle:Agenda");
		return $oRep->getScheduleByCont($oContract,$oDate);
	}
	public function getGroupDaySchedule($oGroup,$oDate,$option){
		$oRep = $this->getRepository("BoAdminBundle:Agenda");
		if($oGroup){
			$aSchedule = $oRep->getScheduleByGroup($oGroup,$oDate);
			return $this->getScheduleByDay($aSchedule,$oDate,$option);
		}
		return array();
	}
	public function getContractDaySchedule($oContract,$oDate,$option){
		$oRep = $this->getRepository("BoAdminBundle:Agenda");
		if($oContract){
			$aSchedule = $oRep->getScheduleByCont($oContract,$oDate);
			if(count($aSchedule)==0) return array();
			$bNoshow = $this->isNoshowForArray($aSchedule,$oDate,$option);
			if($bNoshow==true){
				$oSchedule = $this->checkNoshowFor($aSchedule,$oDate,$option);
				if($oSchedule!=null) return array($oSchedule);
			}
			return $this->getScheduleByDay2($aSchedule,$oDate,$option);
		}
		return array();
	}
	//return an agenda object or null if there is not
	private function checkNoshowFor($aSchedule,$oDate,$option){
		if(count($aSchedule)==0) return null;
		foreach($aSchedule as $oSchedule){
			$bNoshow = $this->isNoshow($oSchedule,$oDate,$option);
			$oSchedule = $this->getScheduleForDate($oSchedule,$oDate,$option);
			if($bNoshow==true and $oSchedule!=null) return $oSchedule;	
		}
		return null;
	} 
	/*
	* get schedules without ones where teacher is absent
	* @param array of schedule
	* @return array of schedule 
	*/
	public function getScheduleWithAbs($aSchedule,$oDate,$option){
		$aResult = array();
		$ids = array();
		foreach($aSchedule as $oSchedule){
			$oEmployee = $oSchedule->getEmployee();
			if($oEmployee==null) continue;
			if($this->isEmployeeAbsent($oEmployee,$oDate,$option)==true and $this->isNoshow($oSchedule,$oDate,$option)==false){
				//Continue when there is not a substitution on this schedule
				$aSubstitution = $this->getSubsByHolder($oSchedule,$oDate,$option);
				//If there no substitution continue
				if($aSubstitution==null) continue;
			}
			$aResult[] = $oSchedule;
		}
		return $aResult;
	}
	public function getSubsByHolder($oSchedule,$oDate,$option){
		$aResult = array();
		$oEmployee = $oSchedule->getEmployee();
		if($oEmployee==null) return $aResult;
		$oRepSub = $this->getRepository('BoAdminBundle:Substitution');
		$aSubstitution = $oRepSub->getByHolderAndDate($oEmployee->getId(),$oDate,$option);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE){
				$aResult[] = $oSubstitution;
			}elseif($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO){
				 $aResult[] = $oSubstitution;	
			}
		}
		return $aResult;
	}
	protected function getEmployeeScheduled($employee,$oDate,$option){
		$aSubs = array();
		$aResult = array();
		$aSchedule=$this->getDaySchedule($employee,$oDate,$option);
		$aSubstitution = $this->getByDateAndTimeBis($employee->getId(),$oDate,$option);
		//if there is no substitution then return formated array of schedule
		if(count($aSubstitution)==0) return $this->getIndexedEntities($aSchedule,1);
		//if there is no substitution then return formated array of schedule
		if(count($aSchedule)==0) return $this->getIndexedEntities($aSubstitution,2);		
		foreach($aSubstitution as $oSub){
			foreach($aSchedule as $index=>$oSch){
				if($oSch->getStartpm()<$oSub->getStartpm() and $oSub->getStartpm()<$oSch->getEndpm() and !isset($aSubs[$oSub->getId()])){
					$aResult[] = array('type='=>2);
					$aSubs[$oSub->getId()] = $oSub->getId();
				}elseif($oSch->getEndpm()<=$oSub->getStartpm()){
					//$aResult[] = array('type='=>1,'object'=>$oSch);
					$aResult[] = array('type='=>1);
				}elseif(isset($aSchedule[$index+1]) and $aSchedule[$index+1]->getStartpm()>$oSub->getEndpm() and !isset($aSubs[$oSub->getId()])){
					$aSubs[$oSub->getId()] = $oSub->getId();
					$aResult[] = array('type='=>2);
				}else{
					$aResult[] = array('type='=>1);
				}
			}
		}
		return $aResult;
	}
    	/**
    	* get array sorted and indexed of entity by option
    	* @param $aEntities the schedule entity or Substitution entity
    	* @return Array of entities of schedule or Substitution
    	*/
	private function getIndexedEntities($aEntities,$type){
		$aResult = array();
		foreach($aEntities as $oEntity){
			$aResult[] = array('type='>$type,'object'=>$oEntity);
		}
		return $aResult;
	}
	//Get event day
	protected function generateEvents($employee,$aDates){
		$aAM=$this->getDayEvent($employee,$aDates,1);
		$aPM=$this->getDayEvent($employee,$aDates,2);
		return array('am'=>$aAM,'pm'=>$aPM);
	}
	protected function getDayEvent($employee,$aDates,$option){
		$aResult = array();
		foreach($aDates as $key=>$oDate){
			$aSubst = array(); //For verifying the repetition on the substitution's return
			$aSchedule=$this->getDaySchedule($employee,$oDate,$option);
			$aSubstitution = $this->getByDateAndTimeBis($employee->getId(),$oDate,$option);
			if(count($aSchedule)==0 and count($aSubstitution)>0){
				foreach($aSubstitution as $index=>$oSubstitution){
					$aEvent = $this->getSubstByOptionBis($oSubstitution,$employee,$oDate,$option);
					if($aEvent!=null) $aResult[$index][$key] = $aEvent;
				}
			}else{
				//get the schedule of the teacher in taking into account the substitution there exists
				foreach($aSchedule as $index=>$oSchedule){
					$aEvent = $this->getDayScheduleEvent($oSchedule,$oDate,$option,$aSubst);
 					$idsub = isset($aEvent['idsubs'])?$aEvent['idsubs']:null; 
					$aResult[$index][$key] = $aEvent;
					if($idsub!=null and !isset($aSubst[$idsub])) $aSubst[$idsub]=$idsub;
				}
				//Take into account all the substitution which won't be taken before
				foreach($aSubstitution as $oSubstitution){
					$index = $index + 1;
					$idsub = $oSubstitution->getId();
					if(!isset($aSubst[$idsub])){
						$aEvent = $this->getSubstByOptionBis($oSubstitution,$employee,$oDate,$option);
						if($aEvent!=null) $aResult[$index][$key] = $aEvent;
						$aSubst[$idsub]=$idsub;
					}
				}
			}
		}
		return $aResult;
	}
	public function getDaySchedule($oEmployee,$oDate,$option){
		$aSchedule = $this->getSortSchedule($oEmployee,$oDate,$option);	
		return $this->getScheduleByDay($aSchedule,$oDate,$option);
	}
	public function getScheduleForEmployye($employee,$oDate,$option){
		$hour = 0;
		$aSubst = array(); //For checking the repetition on the substitution's return
		//Check absence for the teacher, if true return 0
		$bTeacher = $this->isAbsentEmployee($employee,$oDate,$option);
		if($bTeacher==true or $this->isHolidays($oDate)==true) return null;
		$aSchedule=$this->getDaySchedule($employee,$oDate,$option);
		$aSubstitution = $this->getByDateAndTimeBis($employee->getId(),$oDate,$option);
		if(count($aSchedule)==0 and count($aSubstitution)>0){
			foreach($aSubstitution as $index=>$oSubstitution){
				$hour = $hour+$this->getHourSubByOption($oSubstitution,$option);
			}
		}else{
			foreach($aSchedule as $index=>$oSchedule){
				$aDaySched = $this->getDayHourScheduled($oSchedule,$oDate,$option,$aSubst);
				//if the schedule is substitution then get its id
 				$idsub = $aDaySched['idsubs']!=null?$aDaySched['idsubs']:null; 
				//add the hour returned
				$hour = $hour+$aDaySched['hour'];
				//Add the id to the array
				if($idsub!=null and !isset($aSubst[$idsub])) $aSubst[$idsub]=$idsub;
			}
		}
		return $hour; 
	}
	protected function getScheduleByDay($aSchedule,$oDate,$option){
		$idcontract = null;
		$aResult = $aId = array();
		foreach($aSchedule as $oSchedule){
			$oSchedule = $this->getScheduleForDate($oSchedule,$oDate,$option);
			$aOL = $this->checkOverLap($aId,$oSchedule,$option);
			$aId = $aOL[0];
			$bool = $aOL[1];
			if($oSchedule!=null and $bool==true){
				$aResult[] = $oSchedule;
			}
		}
		return $aResult;
	}
	protected function getScheduleByDay2($aSchedule,$oDate,$option){
		$aResult = $aId = array();
		foreach($aSchedule as $oSchedule){
			$oEmployee = $oSchedule->getEmployee();
			if($this->isEmployeeAbsent($oEmployee,$oDate,$option)==true) continue;
			$oSchedule = $this->getScheduleForDate($oSchedule,$oDate,$option);
			//Array of ids contract and a boolean variable to test if the schedule is good to display
			$aOL = $this->checkOverLap($aId,$oSchedule,$option);
			//get the contracts id treated
			$aId = $aOL[0];
			//get the boolean variable to test if we must take in account the schedule
			$bool = $aOL[1];
			if($oSchedule!=null and $bool==true){
				$aResult[] = $oSchedule;
			}
		}
		//return the list of schedules
		return $aResult;
	}
	/*
	* check if the schedule can be displayed
	*/
	private function checkOverLap($aId,$oSchedule,$option){	
		//if $oSchedule==null return false 
		if($oSchedule==null) return array($aId,false); 
		$oContract = $oSchedule->getContracts();
		if(!$oContract) return array($aId,true);
		$idcontract = $oContract->getId();
		if(!isset($aId[$idcontract])){
			$aId[$idcontract][] = $oSchedule;
			return array($aId,true);
		}
		$aSch = $aId[$idcontract];	
		foreach($aSch as $oSch){
			if($this->isTimeBetween($oSchedule,$oSch,$option)==false){
				$aId[$idcontract][] = $oSchedule;
			 	return array($aId,true);
			}
		}
		return array($aId,false);
	}
	protected function isTimeBetween($oEntity1,$oEntity2,$option){
		if($option == self::OPTION_DAY_ONE and $oEntity1->getStartam()>$oEntity2->getStartam() and $oEntity1->getStartam()<$oEntity2->getEndam()) return true;
		if($option == self::OPTION_DAY_ONE and $oEntity1->getEndam()>$oEntity2->getStartam() and $oEntity1->getEndam()<$oEntity2->getEndam()) return true;
		if($option == self::OPTION_DAY_TWO and $oEntity1->getStartpm()>$oEntity2->getStartpm() and $oEntity1->getStartpm()<$oEntity2->getEndpm()) return true;
		if($option == self::OPTION_DAY_TWO and $oEntity1->getEndpm()>$oEntity2->getStartpm() and $oEntity1->getEndpm()<$oEntity2->getEndpm()) return true;
		return false;
	}
	protected function getScheduleForDate($oSchedule,$oDate,$option){
		if($option == self::OPTION_DAY_ONE){
			$amhour = $this->getAmHour($oSchedule);	
			if($amhour>0 and $this->existDaySchedule($oSchedule,$oDate)==1){ 
				return $oSchedule;
			}
		}else{
			$pmhour = $this->getPmHour($oSchedule);
			if($pmhour>0 and $this->existDaySchedule($oSchedule,$oDate)==1){
				return $oSchedule;
			}
		}
		return null;
	}
	//Get schedule of teacher by contract object, date and option AM or PM
	protected function getSchByContract($oContract,$oDate,$option){
		$oRepAg = $this->getRepository('BoAdminBundle:Agenda');
		$aSchedule = $oRepAg->getScheduleByCont($oContract,$oDate);
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($option == self::OPTION_DAY_ONE){
				$amhour = $this->getAmHour($oSchedule);	
				if($amhour>0 and $this->existDaySchedule($oSchedule,$oDate)==1) $aResult[] = $oSchedule;
			}else{
				$pmhour = $this->getPmHour($oSchedule);	
				if($pmhour>0 and $this->existDaySchedule($oSchedule,$oDate)==1) $aResult[] = $oSchedule;
			}
		}
		return $aResult;
	}
	//Get schedule of teacher by group object, date and option AM or PM
	protected function getSchByGroup($oGroup,$oDate,$option){
		$oRepAg = $this->getRepository('BoAdminBundle:Agenda');
		$aSchedule = $oRepAg->getScheduleByGroup($oGroup,$oDate);
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($option == self::OPTION_DAY_ONE){
				$amhour = $this->getAmHour($oSchedule);	
				if($amhour>0 and $this->existDaySchedule($oSchedule,$oDate)==1) $aResult[] = $oSchedule;
			}else{
				$pmhour = $this->getPmHour($oSchedule);	
				if($pmhour>0 and $this->existDaySchedule($oSchedule,$oDate)==1) $aResult[] = $oSchedule;
			}
		}
		return $aResult;
	}
	public function getSubsByEmployeeAndDate($oEmployee,$oDate,$option){
		$aResult = array();
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		$aSubstitution = $oRepSubs->getBySubstituteAndDate($oEmployee->getId(),$oDate);
		if(count($aSubstitution)==0) return null;
		foreach($aSubstitution as $oSubstitution){
			if($option == self::OPTION_DAY_ONE){
				$amhour = $this->getAmHour($oSubstitution);	
				if($amhour>0) $aResult[] = $oSubstitution;
			}else{
				$pmhour = $this->getPmHour($oSubstitution);	
				if($pmhour>0)  $aResult[] = $oSubstitution;
			}			
		}
		return null;
	}
	public function isStudentAbsentBySubs($oSubstitution,$oDate,$option){
		$idgroup = $oSubstitution->getIdgroup();
		if($idgroup!=null){
			$oGroup = $this->getRepository('BoAdminBundle:Group')->find($idgroup);
			if($this->isGroupAbsent2($oGroup,$oDate,$option)==true) return true;
		} 
		$idcontract = $oSubstitution->getIdcontract();
		if($idcontract!=null){
			$oContract = $this->getRepository('BoAdminBundle:Contracts')->find($idcontract);
			$aStudent = $oContract->getStudents();
/*
echo count($aStudent);
echo "<br>".$this->getFullnameOf($aStudent[0]);
echo "<br>".intval($this->isStudentAbsent2($aStudent[0],$oDate,$option));
exit(0);
*/
			if(count($aStudent)==1 and $this->isStudentAbsent2($aStudent[0],$oDate,$option)==true) return true;
			$oGroup = $oContract->getGroup();
			if($this->isGroupAbsent2($oGroup,$oDate,$option)==true) return true;
		} 
		return false;
	}
	public function isStudentNoshowBySubs($oSubstitution,$oDate,$option){
		$idgroup = $oSubstitution->getIdgroup();
		if($idgroup!=null){
			$oGroup = $this->getRepository('BoAdminBundle:Group')->find($idgroup);
			if($this->isGroupNoshow($oGroup,$oDate,$option)==true) return true;
		} 
		$idcontract = $oSubstitution->getIdcontract();
		if($idcontract!=null){
			$oContract = $this->getRepository('BoAdminBundle:Contracts')->find($idcontract);
			$aStudent = $oContract->getStudents();
			if(count($aStudent)==1 and $this->isStudentNoshow($aStudent[0],$oDate,$option)==true) return true;
			$oGroup = $oContract->getGroup();
			if($this->isGroupNoshow($oGroup,$oDate,$option)==true) return true;
		} 
		return false;
	}
	public function isThereEmployee($oSubstitution,$oDate,$option){
		$idgroup = $oSubstitution->getIdgroup();
		if($idgroup!=null){
			$oGroup = $this->getRepository('BoAdminBundle:Group')->find($idgroup);
			return $this->isThereForGroup($oGroup,$oDate,$option);
		} 
		$idcontract = $oSubstitution->getIdcontract();
		if($idcontract!=null){
			$oContract = $this->getRepository('BoAdminBundle:Contracts')->find($idcontract);
			$aStudent = $oContract->getStudents();
			if(count($aStudent)==1) return $this->isThereForStudent($oStudent,$oDate,$option);
			$oGroup = $oContract->getGroup();
			return $this->isThereForGroup($oGroup,$oDate,$option);
		} 
		return false;
	}
	public function isThereForGroup($oGroup,$oDate,$option){
		$aAbsence = $this->getGroupAbsByDate($oGroup,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_ONE){
				if($this->getNoShow($oAbsence,$oDate,$option)==1 and $oAbsence->getTeacherpresence()==true)  return true;
				return false;
			}
			if(($oAbsence->getAmorpm()== self::PERIOD_DAY_PM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_TWO){
				if($this->getNoShow($oAbsence,$oDate,$option)==1 and $oAbsence->getTeacherpresence()==true)  return true;
				return false;
			}
		}
		return false;
	}
	public function isThereForStudent($oStudent,$oDate,$option){
		$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_ONE){
				if($this->getNoShow($oAbsence,$oDate,$option)==1 and $oAbsence->getTeacherpresence()==true){
                                    return true;
                                }
				return false;
			}
			if(($oAbsence->getAmorpm()== self::PERIOD_DAY_PM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_TWO){
				if($this->getNoShow($oAbsence,$oDate,$option)==1 and $oAbsence->getTeacherpresence()==true){
                                    return true;
                                }
				return false;
			}
		}
		return false;
	}
	public function getHourByOption($employee,$oDate,$option){
		$hour = 0;
		$holiday = "Hol";
		$aSubst = array(); //For checking the repetition on the substitution's return
		//Check absence for the teacher, if true return 0
		$bTeacher = $this->isAbsentEmployee($employee,$oDate,$option);
		if($bTeacher==true) return $hour;
		if($this->isHolidays($oDate)==true) return $holiday;
		$aSchedule=$this->getDaySchedule($employee,$oDate,$option);
		$aSubstitution = $this->getByDateAndTimeBis($employee->getId(),$oDate,$option);
		if(count($aSchedule)==0 and count($aSubstitution)>0){
			foreach($aSubstitution as $index=>$oSubstitution){
				$hour = $hour+$this->getHourSubByOption($oSubstitution,$option);
			}
		}else{
			foreach($aSchedule as $index=>$oSchedule){
				if($oSchedule->getAvailability()==0 or $oSchedule->getAvailability()==false) continue;
				$aDaySched = $this->getDayHourScheduled($oSchedule,$oDate,$option,$aSubst);

				//if the schedule is substitution then get its id
 				$idsub = $aDaySched['idsubs']!=null?$aDaySched['idsubs']:null; 
				//add the hour returned
				$hour = $hour+$aDaySched['hour'];
				//Add the id to the array
				if($idsub!=null and !isset($aSubst[$idsub])) $aSubst[$idsub]=$idsub;
			}
		}
		return $hour; 
	}
	public function getLegendByOption($employee,$oDate,$option){
		$hour = 0;
		$aSubst = array(); //For checking the repetition on the substitution's return
		//Check absence for the teacher, if true return 0
		$bTeacher = $this->isAbsentEmployee($employee,$oDate,$option);
		if($bTeacher==true) return "ABS";
		$aSchedule=$this->getDaySchedule($employee,$oDate,$option);
		$aSubstitution = $this->getByDateAndTimeBis($employee->getId(),$oDate,$option);
		if(count($aSchedule)==0 and count($aSubstitution)>0){
			foreach($aSubstitution as $index=>$oSubstitution){
				$hour = $hour+$this->getHourSubByOption($oSubstitution,$option);
			}
		}else{
			foreach($aSchedule as $index=>$oSchedule){
				if($oSchedule->getAvailability()==0 or $oSchedule->getAvailability()==false) continue;
				$aDaySched = $this->getDayHourScheduled($oSchedule,$oDate,$option,$aSubst);

				//if the schedule is substitution then get its id
 				$idsub = $aDaySched['idsubs']!=null?$aDaySched['idsubs']:null; 
				//add the hour returned
				$hour = $hour+$aDaySched['hour'];
				//Add the id to the array
				if($idsub!=null and !isset($aSubst[$idsub])) $aSubst[$idsub]=$idsub;
			}
		}
		if($hour>0) return "P";
		return "ABS"; 
	}
        
	public function getStudentByOption($employee,$oDate,$option){
		$hour = 0;
		$holiday = "Hol";
		$aSubst = array(); //For checking the repetition on the substitution's return
		//Check absence for the teacher, if true return 0
		$bTeacher = $this->isAbsentEmployee($employee,$oDate,$option);
		if($bTeacher==true) return null;
		if($this->isHolidays($oDate)==true) return null;
		$aSchedule=$this->getDaySchedule($employee,$oDate,$option);
		$aSubstitution = $this->getByDateAndTimeBis($employee->getId(),$oDate,$option);
		if(count($aSchedule)==0 and count($aSubstitution)>0){
			$aTab = array();		
			foreach($aSubstitution as $oSubstitution){
				$sStudent = $this->getStudentBySubs($oSubstitution);
				if(!isset($aTab[$sStudent])) $aTab[$sStudent]=$sStudent;
			}
			return join(",",$aTab);
		}else{
			$aTab = array();
			foreach($aSchedule as $oSchedule){
				$sStudent = $this->getStudentBySch($oSchedule);
				if(!isset($aTab[$sStudent])) $aTab[$sStudent]=$sStudent;
			}
			return join(",",$aTab);
		}
		return null; 
	}
	public function getStudentBySubs($oSubstitution){
		$idgroup = $oSubstitution->getIdgroup();
		if($idgroup!=null and $idgroup>0){
			$oGroup = $this->getRepository('BoAdminBundle:Group')->find($idgroup);	
			return $oGroup->getName();	
		}
		$idcontract = $oSubstitution->getIdcontract();
		if($idcontract!=null){
			$oContract = $this->getRepository('BoAdminBundle:Contracts')->find($idcontract);
			$aStudent = $oContract->getStudents();
			if(count($aStudent)==1 and $oStudent=$aStudent[0]) return $this->getFullnameOf($oStudent);
			$oGroup = $oContract->getGroup();
			if($oGroup) return $oGroup->getName();
		}
		return null;
	}
	public function getStudentBySch($oSchedule){
		$oGroup = $oSchedule->getGroup();
		if($oGroup) return $oGroup->getName();	
		$oContract = $oSchedule->getContracts();
		if($oContract){
			$aStudent = $oContract->getStudents();
			if(count($aStudent)==1 and $oStudent=$aStudent[0]) return $this->getFullnameOf($oStudent);
			$oGroup = $oContract->getGroup();
			if($oGroup) return $oGroup->getName();
		}
		return null;
	}
	public function getStudentHour($oStudent,$oDate,$option){
		$hour = 0;
		//Check absence for the student, if true return 0 else fale return 1
		$bStudAbs = $this->isStudentAbsent2($oStudent,$oDate,$option);
		if($bStudAbs==true) return $hour;
		//if($this->isHolidays($oDate)==true) return $holiday;
		$aSchedule = $this->getStudentDaySchedule($oStudent,$oDate,$option);
		foreach($aSchedule as $oSchedule){
			if($this->isHolidaysBy($oDate,$oSchedule)==true) return 0;
			$hour = $hour + $this->getHourSchByOption($oSchedule,$option);
		}
		return $hour; 
	}
	public function getStudentHour2($oContract,$oStudent,$oDate,$option){
		$hour = 0;
                
		$oRepAg = $this->getRepository('BoAdminBundle:Agenda');
                
		//Check absence for the student, if true return 0 else fale return 1
		$bStudAbs = $this->isStudentAbsent2($oStudent,$oDate,$option);
                
		if($bStudAbs==true or $this->isHolidays($oDate,$option)==true) return $hour;
		$aSubstitution = $this->getSubsByContract($oContract,$oDate,$option);
                
		if(count($aSubstitution)>0) return $this->sumHourBySubs($aSubstitution);
                
		$aSchedule = $this->getContractDaySchedule($oContract,$oDate,$option);
		$aSchedule = $this->getScheduleWithAbs($aSchedule,$oDate,$option);
                
		return $this->sumHourBySch($aSchedule);
	}
	//get student used hour
	public function getStudentUsedHour($oContract,$oStudent,$oDate,$option){
		$hour     = 0;
                $bStudAbs = false;
                
		$oRepAg = $this->getRepository('BoAdminBundle:Agenda');
                		
                if(null === $oStudent){
                    //The case where the contract has many student
                    $oGroup   = $oContract->getGroup();
                    if(null !== $oGroup){
                        $bStudAbs = $this->isGroupAbsent2($oGroup,$oDate,$option);
                    }                
                }else{
                    //Check absence for the student, $bStudAbs equal 0 else false return 1
                    $bStudAbs = $this->isStudentAbsent2($oStudent,$oDate,$option);                    
                }
                
		//Check if there is absence or Holiday the AP or PM then return O
		if($bStudAbs == true or $this->isHolidays($oDate,$option) == true) return $hour;
                
		$aSubstitution = $this->getSubsByContract($oContract,$oDate,$option);
                
		$aSchedule = $this->getContractDaySchedule($oContract,$oDate,$option);
                
		$aSchedule = $this->getScheduleWithAbs($aSchedule,$oDate,$option);
                
		if(count($aSubstitution)>0 and $this->sumHourBySubs($aSubstitution)>=$this->getSchUhBy($aSchedule,$option)){ 
                    return $this->sumHourBySubs($aSubstitution);
                }
                
		return $this->getSchUhBy($aSchedule,$option);
	}
        
	//get student hour
	//@Param : $oContract,$oStudent,$oDate=null
	//@return : array of hours of AM and PM and teacher ids and names
	public function getStudentArrayHour($oContract,$oStudent,$oDate=null){
                $bAbsam = $bAbspm = false;
            
		if($oDate==null){
                    $oDate = $this->getToday();                    
                } 
                
		$iHouram = $iHourpm = 0;
                
		$sAmteacher = $sPmteacher = null;
                
		$oHolidayAM = $this->getHolidaysBy($oDate,1);
		$oHolidayPM = $this->getHolidaysBy($oDate,2);
                
                if(null === $oStudent){
                    //The case where the contract has many student
                    $oGroup   = $oContract->getGroup();
                    if(null !== $oGroup){
                        $bAbsam = $this->isGroupAbsent2($oGroup,$oDate,1);
                    }                
                }else{
                    //If the student is not absent for am then get the student hour used 
                    $bAbsam = $this->isStudentAbsent2($oStudent,$oDate,1);               
                }               

		if($bAbsam == false and $oHolidayAM == null){
			$iHouram    = $this->getStudentUsedHour($oContract,$oStudent,$oDate,1);
			$sAmteacher = $this->getStudentTeacher($oContract,$oDate,1);
		}

                
                if(null === $oStudent){
                    //The case where the contract has many student
                    $oGroup   = $oContract->getGroup();
                    if(null !== $oGroup){
                        $bAbsam = $this->isGroupAbsent2($oGroup,$oDate,2);
                    }                
                }else{
                    //If the student is not absent for pm then get the student hour used 
                    $bAbspm = $this->isStudentAbsent2($oStudent,$oDate,2);           
                }                 
                
		if($bAbspm == false and $oHolidayPM == null){
			$iHourpm = $this->getStudentUsedHour($oContract,$oStudent,$oDate,2);
			$sPmteacher = $this->getStudentTeacher($oContract,$oDate,2);
		}
                
		return array('am'=>$iHouram,'tam'=>$sAmteacher,'tnam'=>$this->getEmployeeNameById($sAmteacher),'pm'=>$iHourpm,'tpm'=>$sPmteacher,'tnpm'=>$this->getEmployeeNameById($sPmteacher));
	}
        
	//get group hour
	//@Param : $oContract,$oStudent,$oDate=null
	//@return : array of hours of AM and PM and teacher ids and names
	public function getGroupArrayHour($oSchedule,$oStudent=null,$oDate=null){
		$oGroup = $oSchedule->getGroup();
		$oContract = $oSchedule->getContracts();
		if($oGroup==null and $oContract!=null) $oGroup = $oContract->getGroup();
		if($oDate==null) $oDate=$this->getToday();
		$iHouram=$iHourpm=0;
		$sAmteacher = $sPmteacher = 0;
		//check if this date is a holiday
		$bAmHoliday = $this->isHolidaysBy($oDate,$oSchedule,1);
		$bPmHoliday = $this->isHolidaysBy($oDate,$oSchedule,2);
		if($this->isWeekend($oDate)==true or ($bAmHoliday==true and $bPmHoliday==true)){
			return array('am'=>$iHouram,'tam'=>$sAmteacher,'tnam'=>$sAmteacher==0?null:$this->getEmployeeNameById($sAmteacher),'pm'=>$iHourpm,'tpm'=>$sPmteacher,'tnpm'=>$sPmteacher==0?null:$this->getEmployeeNameById($sPmteacher));
		}
		//If the student is not absent for am then get the student hour used 
		$bAbsam = $this->isGroupAbsent2($oGroup,$oDate,1);
		if($bAbsam==false and $bAmHoliday==false){
			if($oGroup==null and $oContract!=null){//One contract for many students
				$iHouram = $this->getContractHour($oContract,$oDate,1);
				$sAmteacher = $this->getStudentTeacher($oContract,$oDate,1);
			}elseif($oGroup!=null){
				$iHouram = $this->getGroupUsedHourBy($oGroup,$oDate,1);
				$sAmteacher = $this->getGroupTeacher($oGroup,$oDate,1);
			}
		}
		//If the student is not absent for pm then get the student hour used 
		$bAbspm = $this->isGroupAbsent2($oGroup,$oDate,2);
		if($bAbspm==false and $bPmHoliday==false){
			if($oGroup==null and $oContract!=null){//One contract for many students
				$iHourpm = $this->getContractHour($oContract,$oDate,2);
				$sPmteacher = $this->getStudentTeacher($oContract,$oDate,2);
			}elseif($oGroup!=null){
				$iHourpm = $this->getGroupUsedHourBy($oGroup,$oDate,2);
				$sPmteacher = $this->getGroupTeacher($oGroup,$oDate,2);
			}
		}
		return array('am'=>$iHouram,'tam'=>$sAmteacher,'tnam'=>$sAmteacher==0?null:$this->getEmployeeNameById($sAmteacher),'pm'=>$iHourpm,'tpm'=>$sPmteacher,'tnpm'=>$sPmteacher==0?null:$this->getEmployeeNameById($sPmteacher));
	}

	//get substitution usedhour by option
	private function getSubsUhBy($aSubstitution,$option){
		$hour = 0;
		foreach($aSubstitution as $oSubstitution){
			$hour = $hour+floatval($this->getRealHourByOption($oSubstitution,$option)); 
		}
		return $hour;
	}
	//get schedule usedhour by option
	//@return Float
	private function getSchUhBy($aSchedule,$option){
		$hour = 0;
		foreach($aSchedule as $oSchedule){
			$hour = $hour+floatval($this->getRealHourScheduled($oSchedule,$option)); 
		}
		return $hour;
	}
	//@return Float
	private function sumHourBySch($aSchedule){
		$hour = 0;
		foreach($aSchedule as $oSchedule){
			$hour = $hour+floatval($oSchedule->getHourperday()); 
		}
		return $hour;
	}
	//@return Float
	private function sumHourBySubs($aSubstitution){
		$hour = 0;
		foreach($aSubstitution as $oSubstitution){
			$hour = $hour+floatval($oSubstitution->getHour()); 
		}
		return $hour;
	}
	//@return Float
	private function sumHour($aTab){
		$hour = 0;
		foreach($aTab as $dNum){
			$hour = $hour+floatval($dNum); 
		}
		return $hour;
	}
	public function getStudentTeacher($oContract,$oDate,$option){
		$aIdTeacher = $aIds = array(); //For verifying the repetition on the substitution's return
		$aSchedule=$this->getSchByContract($oContract,$oDate,$option);
		$oSchedule = $this->checkNoshowFor($aSchedule,$oDate,$option);
		if($oSchedule!=null){
			return "Noshow";
		}
		$aSubstitution = $this->getSubsByContract($oContract,$oDate,$option);
		foreach($aSubstitution as $oSubstitution){
			$idsubstitute = $oSubstitution->getIdsubstitute();
			$aIds = $this->getOverlapBetween($aSubstitution[0],$aSchedule,$option,$aIds);
			$aIdTeacher[] = $idsubstitute;
		}
		//get the schedule of the teacher in taking into account the substitution there exists
		foreach($aSchedule as $oSchedule){
			$oEmployee = $oSchedule->getEmployee();
			if($oEmployee==null) continue;
			if($this->isEmployeeAbsent($oEmployee,$oDate,$option)==true) continue;
			if(isset($aIds[$oSchedule->getId()])) continue;
			if($this->isScheduledFor($oSchedule,$option)==false) continue;
			//Check if the student is absent, if yes not take into account
			$bStudentAbsent = $this->isAbsentStudent2($oSchedule,$oDate,$option);
			if($bStudentAbsent=="error" or $bStudentAbsent==true) continue; 
			$aIdTeacher[] = $oSchedule->getEmployee()->getId();
		}
		return join(",",$aIdTeacher);
	}
	public function getGroupTeacher($oGroup,$oDate,$option){
		$aIdTeacher = $aIds = array(); //For verifying the repetition on the substitution's return
		$aSchedule=$this->getSchByGroup($oGroup,$oDate,$option);
		$aSubstitution = $this->getSubsByGroup($oGroup,$oDate,$option);
		foreach($aSubstitution as $oSubstitution){
			$idsubstitute = $oSubstitution->getIdsubstitute();
			$aIds = $this->getOverlapBetween($aSubstitution[0],$aSchedule,$option,$aIds);
			$aIdTeacher[] = $idsubstitute;
		}
		//get the schedule of the teacher in taking into account the substitution there exists
		foreach($aSchedule as $oSchedule){
			$oEmployee = $oSchedule->getEmployee();
			if($oEmployee==null) continue;
			if($this->isEmployeeAbsent($oEmployee,$oDate,$option)==true) continue;
			if(isset($aIds[$oSchedule->getId()])) continue;
			if($this->isScheduledFor($oSchedule,$option)==false) continue;
			//Check if the student is absent, if yes not take into account
			$bStudentAbsent = $this->isAbsentStudent2($oSchedule,$oDate,$option);
			if($bStudentAbsent=="error" or $bStudentAbsent==true) continue; 
			$aIdTeacher[] = $oSchedule->getEmployee()->getId();
		}
		return join(",",$aIdTeacher);
	}
	protected function isScheduledContract($oContract,$oDate){
		if($oContract->getGroup() and $oGroup = $oContract->getGroup()) return $this->isScheduledGroup($oGroup,$oDate);
		$oRepAg = $this->getRepository('BoAdminBundle:Agenda');
		$aSchedule = $oRepAg->getScheduleByCont($oContract,$oDate);
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		$aSubstitution = $oRepSubs->getByContract($oContract,$oDate);
		if(count($aSchedule)>0 or count($aSubstitution)>0) return true;
		return false;
	}
	protected function isScheduledGroup($oGroup,$oDate){
		$oRepAg = $this->getRepository('BoAdminBundle:Agenda');
		$aSchedule = $oRepAg->getScheduleByGroup($oGroup,$oDate);
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		$aSubstitution = $oRepSubs->getByGroup($oGroup,$oDate);
		if(count($aSchedule)>0 or count($aSubstitution)>0) return true;
		return false;
	}
	protected function getScheduleByGroup($oGroup){
		$aContracts = $oGroup->getContracts();
		foreach($aContracts as $oContract){
			$schedules = $this->getRepository('BoAdminBundle:Agenda')->getAllByContractDate($oContract);
			foreach($schedules as $oSchedule){
				if(isset($aSchedule[$oSchedule->getId()])) continue;
				$aSchedule[$oSchedule->getId()] = $oSchedule;
			}
		}
		return $aSchedule;
	}
	public function isScheduledFor($oEntity,$option){
		if($oEntity==null) return $oEntity;
		if($this->getAmHour($oEntity)>0 and $option == self::OPTION_DAY_ONE){
			return true;
		}
		if($this->getPmHour($oEntity)>0 and $option == self::OPTION_DAY_TWO){
			return true;
		}
		return false;
	}
	public function getRealScheduled($aSchedule,$oDate){
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($this->existDaySchedule($oSchedule,$oDate)==1) $aResult[] = $oSchedule;
		}
		return $aResult;
	}
	public function getRealScheduledByCont($oContract,$oDate){
		$aSchedule = $this->getScheduleByContract($oContract,$oDate);
		return $this->getRealScheduled($aSchedule,$oDate);
	}
	public function getRealScheduledBySch($oSchedule,$oDate){
		if($oSchedule->getContracts()==null) return array();
		$oContract = $oSchedule->getContracts();	
		$aSchedule = $this->getScheduleByContract($oContract,$oDate);
		return $this->getRealScheduled($aSchedule,$oDate);
	}
	public function getContractHour($oContract,$oDate,$option){
		$hour = 0;
		//if($this->isHolidays($oDate)==true) return $holiday;
		$aSchedule = $this->getContractDaySchedule($oContract,$oDate,$option);
		foreach($aSchedule as $oSchedule){
			if($this->isHolidaysBy($oDate,$oSchedule)==true) return 0;
			$hour = $hour + $this->getHourSchByOption($oSchedule,$option);
		}
		return $hour; 
	}
	public function getGroupHour($oGroup,$oDate,$option){
		$hour = 0;
		$aSchedule = $this->getGroupDaySchedule($oGroup,$oDate,$option);
		foreach($aSchedule as $oSchedule){
			if($this->isHolidaysBy($oDate,$oSchedule)==true) return 0;
			$hour = $hour + $this->getHourSchByOption($oSchedule,$option);
		}
		return $hour; 
	}
	/*Verify if the student is absent this date.
	*@Param: $oStudent, $oDate, $iOption
	*$iOption: 1=AM and 2=PM
	*/ 
	protected function isStudentAbsent2($oStudent,$oDate,$option){
		if($oStudent==null) return $oStudent;
		$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM || $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) && $option == self::OPTION_DAY_ONE){
				if($this->getNoShow($oAbsence,$oDate,$option)==1)  return false;
				return true;
			}
			if(($oAbsence->getAmorpm()== self::PERIOD_DAY_PM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option == self::OPTION_DAY_TWO){
				if($this->getNoShow($oAbsence,$oDate,$option)==1)  return false;
				return true;
			}
		}
		return false;
	}
	/*Verify if the student is noshow this date.
	*@Param: $oStudent, $oDate, $iOption
	*$iOption: 1=AM and 2=PM
	*/ 
	protected function isStudentNoshow($oStudent,$oDate,$option){
		if($oStudent==null) return $oStudent;
		$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option === self::OPTION_DAY_ONE){
				if($this->getNoShow($oAbsence,$oDate,$option)==1)  return true;
				return false;
			}
			if(($oAbsence->getAmorpm()== self::PERIOD_DAY_PM or $oAbsence->getAmorpm()== self::PERIOD_DAY_ALL) and $option === OPTION_DAY_TWO){
				if($this->getNoShow($oAbsence,$oDate,$option)==1)  return true;
				return false;
			}
		}
		return false;
	}
	/*Verify if the Group is absent this date.
	*@Param: $oStudent, $oDate, $iOption
	*$iOption: 1=AM and 2=PM
	*/ 
	protected function isGroupAbsent2($oGroup,$oDate,$option){
		if($oGroup==null){
                    return $oGroup;
                }
		$aAbsence = $this->getGroupAbsByDate($oGroup,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM or $oAbsence->getAmorpm() == self::PERIOD_DAY_ALL) and $option === OPTION_DAY_ONE){
				if($this->getNoShow($oAbsence,$oDate,$option) == 1){
                                    return false;
                                }
				return true;
			}
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_PM or $oAbsence->getAmorpm() == self::PERIOD_DAY_ALL) and $option === OPTION_DAY_TWO){
				if($this->getNoShow($oAbsence,$oDate,$option) == 1){
                                    return false;
                                }
				return true;
			}
		}
		return false;
	}
	/*Verify if the Group is noshow this date.
	*@Param: $oStudent, $oDate, $iOption
	*$iOption: 1=AM and 2=PM
	*/ 
	protected function isGroupNoshow($oGroup,$oDate,$option){
		if($oGroup==null) return $oGroup;
		$aAbsence = $this->getGroupAbsByDate($oGroup,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM or $oAbsence->getAmorpm()== PERIOD_DAY_ALL) and $option === OPTION_DAY_ONE){
				if($this->getNoShow($oAbsence,$oDate,$option) == 1)  return true;
				return false;
			}
			if(($oAbsence->getAmorpm()== PERIOD_DAY_PM or $oAbsence->getAmorpm()== PERIOD_DAY_ALL) and $option === OPTION_DAY_TWO){
				if($this->getNoShow($oAbsence,$oDate,$option) == 1)  return true;
				return false;
			}
		}
		return false;
	}
	/*Check if it is a noshow for the student this date.
	*@Param: $oStudent, $oDate, $iOption
	*$iOption: 1=AM and 2=PM
	*@Return true if it is noshow and false else
	*/
	protected function isNoshow($oSchedule,$oDate,$option){
		$aAbsence = null;
		$oContract = $oSchedule->getContracts();
		if($oContract==null and $oSchedule->getGroup()==null) return false;
		if($oSchedule->getGroup()==null){
			$aStudent = $oContract->getStudents();
			if(count($aStudent)==1){ 
				//Get student absence
				$aAbsence = $this->getStudentAbsByDate($aStudent[0],$oDate);
			}else{
				//Get absence by contract
				//return true when all students are in noshow or false otherwise 
				foreach($aStudent as $oStudent){
					$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
					//if there is an absence entity for this day then get the object else return false 
					if(count($aAbsence)==1) $oAbsence=$aAbsence[0];
					else return false; //In this case at least one student is not absent
					if($this->getBoolNoshow($oAbsence,$oDate,$option)==false) return false;
				}
				return true;
			}
		}else{
			$oGroup = $oSchedule->getGroup();
			$aAbsence = $this->getGroupAbsByDate($oGroup,$oDate);		
		}
		if($aAbsence!=null and isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			return $this->getBoolNoshow($oAbsence,$oDate,$option);
		}
		return false;
	}
	//Return true when it's noshow for the day period or false otherwise
	private function getBoolNoshow($oAbsence,$oDate,$option){
		if($oAbsence->getAmorpm()== PERIOD_DAY_ALL and $option == self::OPTION_DAY_THREE){
			if($this->getNoShow($oAbsence,$oDate,$option)==1)  return true;
			return false;
		}
		if(($oAbsence->getAmorpm() == self::PERIOD_DAY_AM or $oAbsence->getAmorpm()== PERIOD_DAY_ALL) and $option == self::OPTION_DAY_ONE){
			if($this->getNoShow($oAbsence,$oDate,$option)==1)  return true;
			return false;
		}
		if(($oAbsence->getAmorpm()== PERIOD_DAY_PM or $oAbsence->getAmorpm()== PERIOD_DAY_ALL) and $option == self::OPTION_DAY_TWO){
			if($this->getNoShow($oAbsence,$oDate,$option)==1)  return true;
			return false;
		}
		return false;
	}
	private function isNoshowForArray($aSchedule,$oDate,$option){
		if(count($aSchedule)==0) return false;
		return $this->isNoshow($aSchedule[0],$oDate,$option);
	}
	//Get hour scheduled for a employee by date and option (1=AM and 2=PM)
	private function getDayHourScheduled($oSchedule,$oDate,$option,$aSubst){
		$oContract = null;
		$sStudentAbsence = null;
		//Checking absence for the student
		if($oSchedule->getContracts()){
			$oContract = $oSchedule->getContracts();
			//get absence student if the contrat is not group contract or the number student is equal to 1 when it's a simple contract
			if($oSchedule->getGroup()!=null){ 
				$oGroup = $oSchedule->getGroup();
				$aContract = $oGroup->getContracts();
				if(count($aContract)>0 and $oContract = $aContract[0])
				$sStudentAbsence = $this->getAbsenceForStudent($oContract,$oDate,$option);
			}elseif($oContract!=null and count($oContract->getStudents())==1)
				$sStudentAbsence = $this->getAbsenceForStudent($oContract,$oDate,$option);
		}
		$oEmployee = $oSchedule->getEmployee();
		//Check if there exists a substitution for this date and for the option
		$oSubstitution = $this->getSubsBySchedule($oSchedule,$oDate,$option);
		//If there is no schedule and no substitution 
		if($oSchedule==null and $oSubstitution==null) return array('hour'=>0,'idsubs'=>null);
		if($oSubstitution!=null and !isset($aSubst[$oSubstitution->getId()])) return array('hour'=>$this->getHourSubByOption($oSubstitution,$option),'idsubs'=>$oSubstitution->getId());
		//If absence of student equal null
		if($sStudentAbsence!=null) return array('hour'=>0,'idsubs'=>null);
		return array('hour'=>$this->getHourSchByOption($oSchedule,$option),'idsubs'=>null,'idsch'=>$oSchedule->getId());
	}
	//Get hour scheduled for a employee by date and option (1=AM and 2=PM)
	private function getOptionStudentHour($oSchedule,$oDate,$option){
		return $this->getHourSchByOption($oSchedule,$option);
	}
	//Get hour of substitution for a employee by date and option (1=AM and 2=PM)
	private function getHourSubByOption($oSubstitution,$option){
		if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE){ 
			$amhour = $this->getAmHour($oSubstitution);
			if($amhour<=4 and $oSubstitution->getHour()<=$amhour) return $oSubstitution->getHour();
			if($amhour>=3) return $this->getAmHourWithBreak($oSubstitution);
			return $amhour;	
		}
		if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and $oSubstitution->getStartpm()<$this->getTime(15,30)){
			$pmhour = $this->getPmHour($oSubstitution);
			if($pmhour<=4 and $oSubstitution->getHour()<=$pmhour) return $oSubstitution->getHour();
			if($pmhour>=3) return $this->getPmHourWithBreak($oSubstitution);
			return $pmhour;	
		}elseif($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_THREE){
			$pmhour = $this->getPmHour($oSubstitution);
			if($pmhour<=4 and $oSubstitution->getHour()<=$pmhour) return $oSubstitution->getHour();
			if($pmhour>=3) return $this->getPmHourWithBreak($oSubstitution);
			return $pmhour;	
		}
		return 0;
	}
	//Get hour of substitution for a employee by date and option (1=AM and 2=PM)
	private function getHourSchByOption($oSchedule,$option){
		$amhour = $this->getAmHour($oSchedule);
		$pmhour = $this->getPmHour($oSchedule);
		$total = floatval($amhour)+floatval($pmhour);
		if($this->getAmHour($oSchedule)>0 and $option == self::OPTION_DAY_ONE){ 
			if($oSchedule->getContracts()!=null or $oSchedule->getGroup()!=null){
				if($amhour<=4 and $oSchedule->getHourperday()<=$amhour) return $oSchedule->getHourperday();
				if($amhour>=3) return $this->getAmHourWithBreak($oSchedule);
			}
			return $amhour;	
		}
		$startpm = $this->getRealTime($oSchedule->getStartpm()->format("H:i"));
		if($this->getPmHour($oSchedule)>0 and $option == self::OPTION_DAY_TWO and $startpm<$this->getRealTime("15:30")){
			if($oSchedule->getContracts()!=null or $oSchedule->getGroup()!=null){
				if($pmhour<=4 and $oSchedule->getHourperday()<=$pmhour) return $oSchedule->getHourperday();
				if($pmhour>=3) return $this->getPmHourWithBreak($oSchedule);
			}
			return $pmhour;	
		}elseif($this->getPmHour($oSchedule)>0 and $option == self::OPTION_DAY_THREE and $startpm>$this->getRealTime("15:30")){
			if($oSchedule->getContracts()!=null or $oSchedule->getGroup()!=null){
				if($pmhour<=4 and $oSchedule->getHourperday()<=$pmhour) return $oSchedule->getHourperday();
				if($pmhour>=3) return $this->getPmHourWithBreak($oSchedule);
			}
			return $pmhour;	
		}
		return 0;
	}
	public function getDayHourFor($oEmployee,$oDate){
		return array('am'=>$this->getHourByOption($oEmployee,$oDate,1),'pm'=>$this->getHourByOption($oEmployee,$oDate,2));
	}
	public function generateAllSchedule($aEmployees,$aDate){
		$aResult = array();
		foreach($aEmployees as $oEmployee){
			foreach($aDate as $oDate){
				//matin option egale 1
				$am = $this->getHourByOption($oEmployee,$oDate,1);
				//après-midi avant 17 heures option egale 2
				$pm = $this->getHourByOption($oEmployee,$oDate,2);
				//après-midi après 17 heures option egale 3
				$ev = $this->getHourByOption($oEmployee,$oDate,3);
				if($am>0 or $pm>0){
					$aResult[$oEmployee->getId()][$oDate->format("Y-m-d")]['am']=$am;
					$aResult[$oEmployee->getId()][$oDate->format("Y-m-d")]['pm']=$pm;
					$aResult[$oEmployee->getId()][$oDate->format("Y-m-d")]['ev']=$ev;
				}
			}
		}
		return $aResult;
	}
	//Get sorted schedule of active employee schedule  
	private function getSortSchedule($oEmployee,$oDate,$option){
		$aSchedule = $this->getActiveScheduleByDate($oEmployee,$oDate);
		if(count($aSchedule)<2) return $aSchedule;
		$aSchedule = $this->getScheduleByKey($aSchedule,$oDate,$option);
		ksort($aSchedule);
		return $aSchedule;
	}
	//Return the lastest schedule
	private function getUniqueSchedule($aSchedule){
		$token=null;
		$oldestdate = new \DateTime("2000-01-01");
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getStartdate()>$oldestdate){
				$token = $oSchedule;
				$oldestdate = $oSchedule->getStartdate();
			}
		}
		return $token;
	}
	//Get day schedule event for the teacher
	protected function getDayScheduleEvent($oSchedule,$oDate,$option,$aSubs=null){
		//this employee is holder of a contract and is substituted by another teacher
		$oSubsHolder = $this->getSubstBySchedule($oSchedule,$oDate,$option);
		$oEmployee = $oSchedule->getEmployee();
		//This employee substituted some one on another contract
		$oSubstitution = $this->getSubsBySchedule($oSchedule,$oDate,$option);
		//if there are no schedule nor substitution then return null
		if($oSchedule==null and $oSubstitution==null) return $oSchedule;
		//if option equal to 1 get hour of am else get hour of pm
		$hour = $option == self::OPTION_DAY_ONE?$this->getAmHour($oSchedule):$this->getPmHour($oSchedule);	
		if($oSubstitution!=null and $this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option);
		}elseif($oSubstitution and $this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option);
		}
		return $this->getScheduleByOption($hour,$oSchedule,$oDate,$option);
	}
	//created the 2020-01-04
	//Get day schedule event for the student
	protected function getDayStudentEvent($oStudent,$oSchedule,$oDate,$option,$aSubs=null){
		//this employee is holder of a contract and is substituted by another teacher
		$oSubsHolder = $this->getSubstBySchedule($oSchedule,$oDate,$option);
		//This employee substituted some one on another contract
		$oSubstitution = $this->getSubsBySchedule($oSchedule,$oDate,$option);
		//if there are no schedule nor substitution then return null
		if($oSchedule==null and $oSubstitution==null) return $oSchedule;
		//if option equal to 1 get hour of am else get hour of pm
		$hour = $option == self::OPTION_DAY_ONE?$this->getAmHour($oSchedule):$this->getPmHour($oSchedule);	
		if($oSubstitution!=null and $this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option);
		}elseif($oSubstitution and $this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option);
		}
		return $this->getScheduleByOption($hour,$oSchedule,$oDate,$option);
	}

	//Get day schedule event for the teacher
	protected function getOverlapBetween($oSubstitution,$aSchedule,$option,$aIds){
		foreach($aSchedule as $oSchedule){
			if($this->timeisBetween($oSubstitution,$oSchedule,$option)==true) $aIds[$oSchedule->getId()]=$oSchedule->getId();
		}
		return $aIds;
	}

	//Get day schedule event for the teacher
	private function getDaySubsEvent($oSubstitution,$oSchedule,$oDate,$option,$aSubs){
		//this employee is holder of a contract and is substituted by another teacher
		//$oSubsHolder = $this->getSubstBySchedule($oSchedule,$oDate,$option);
		//$oEmployee = $oSchedule->getEmployee();
		if($oSubstitution!=null and $this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option);
		}elseif($oSubstitution!=null and $this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option);
		}
	}
	//Get day schedule event for the student
	private function getDaySchStudEvent($oSchedule,$oDate,$option,$aSubs){
		//This employee substituted some one on another contract
		$oSubstitution = $this->getSubsBySchStud($oSchedule,$oDate,$option);
		//if there are no schedule nor substitution then return null
		if($oSchedule==null and $oSubstitution==null) return $oSchedule;
		//if option equal to 1 get hour of am else get hour of pm
		$hour = $option == self::OPTION_DAY_ONE?$this->getAmHour($oSchedule):$this->getPmHour($oSchedule);	
		if($oSubstitution!=null and $this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option,$oSubstitution->getIdsubstitute());
		}elseif($oSubstitution!=null and $this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option,$oSubstitution->getIdsubstitute());
		}
		return $this->getScheduleByOption($hour,$oSchedule,$oDate,$option);
	}
	//timeisBetween
	//Check if time is between rime range of a schedule
	private function timeisBetween($oSubstitution,$oSchedule,$option){
		if($option == self::OPTION_DAY_ONE and $oSubstitution->getStartam()>=$oSchedule->getStartam() and $oSubstitution->getStartam()<$oSchedule->getEndam()) return true;
		if($option == self::OPTION_DAY_ONE and $oSubstitution->getEndam()>$oSchedule->getStartam() and $oSubstitution->getEndam()<=$oSchedule->getEndam()) return true;
		if($option == self::OPTION_DAY_TWO and $oSubstitution->getStartpm()>=$oSchedule->getStartpm() and $oSubstitution->getStartpm()<$oSchedule->getEndpm()) return true;
		if($option == self::OPTION_DAY_TWO and $oSubstitution->getEndpm()>$oSchedule->getStartpm() and $oSubstitution->getEndpm()<=$oSchedule->getEndpm()) return true;
		return false;
	}
	//Get substitution by schedule for the teacher event
	private function getSubsBySchedule($oSchedule,$oDate,$option){
		$aSubstitution = $this->getSubsForSchedule($oSchedule,$oDate,$option);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE){
				return $oSubstitution;
			}elseif($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO){
				 return $oSubstitution;	
			}
		}
		return null;
	}
	public function getSubsForSchedule($oSchedule,$oDate,$option){
		$aResult = array();
		$oEmployee = $oSchedule->getEmployee();
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		$aSubstitution = $oRepSubs->getBySubstituteAndDate($oEmployee->getId(),$oDate);
		foreach($aSubstitution as $oSubstitution){
			//Get the schedule of the substitution holder
			$aAgenda = $this->getHolderAgenda($oSubstitution,$oDate);
			if($aAgenda==null) continue;
			foreach($aAgenda as $oAgenda){

				//Check if the hour 
				if($this->getAmHour($oAgenda)>0 and $this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and $this->existDaySchedule($oAgenda,$oDate)==1){
					if(($oSubstitution->getStartam()>=$oSchedule->getStartam() and $oSubstitution->getStartam()<$oSchedule->getEndam()) or ($oSchedule->getStartam()>=$oSubstitution->getStartam() and $oSchedule->getStartam()<$oSubstitution->getEndam())) 	$aResult[]=$oSubstitution;
				}
				if($this->getPmHour($oAgenda)>0 and $this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and $this->existDaySchedule($oAgenda,$oDate)==1){
					if(($oSubstitution->getStartpm()>=$oSchedule->getStartpm() and $oSubstitution->getStartpm()<$oSchedule->getEndpm()) or ($oSchedule->getStartpm()>=$oSubstitution->getStartpm() and $oSchedule->getStartpm()<$oSubstitution->getEndpm())) 	$aResult[]=$oSubstitution;
				}
			}
		}
		return $aResult;
	}
	//Get Array of substitution by schedule for the teacher event
	private function getArraySubsBySchedule($oSchedule,$oDate,$option){
		$aSubstitution = $this->getSubsForSchedule($oSchedule,$oDate,$option);
		$aResult = array();
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE) $aResult[] = $oSubstitution;
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO) $aResult[] = $oSubstitution;	
		}
		return $aResult;
	}
	//Get substitution by schedule for the student event
	private function getSubsBySchStud($oSchedule,$oDate,$option){
		$aSubstitution = $this->getSubsForStudent($oSchedule,$oDate,$option);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE) return $oSubstitution;
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO) return $oSubstitution;	
		}
		return null;
	}
	public function getSubsForStudent($oSchedule,$oDate,$option){
		$aResult = $aSubstitution = array();
		$oContract = $oSchedule->getContracts();
		$oGroup = $oSchedule->getGroup();
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		if($oGroup!=null) $aSubstitution = $oRepSubs->getBySubsByGroup($oGroup->getId(),$oDate);
		elseif($oContract!=null) $aSubstitution = $oRepSubs->getBySubsByContract($oContract->getId(),$oDate);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and $this->existDaySchedule($oSubstitution,$oDate)==1) 	$aResult[]=$oSubstitution;
			if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and $this->existDaySchedule($oSubstitution,$oDate)==1) 	$aResult[]=$oSubstitution;
		}
		return $aResult;
	}
	public function getHolderAgenda($oSubstitution,$oDate){
		$oHolder = $oGroup = $oContract = $aAgenda = null;
		if($oSubstitution->getIdholder()!=null){ 
			$oEmployee = $this->getRepository('BoAdminBundle:Employee')->find($oSubstitution->getIdholder());
		}
		if($oSubstitution->getIdgroup()!=null){ 
			$oGroup = $this->getRepository('BoAdminBundle:Group')->find($oSubstitution->getIdgroup());
		}
		if($oSubstitution->getIdContract()!=null){ 
			$oContract = $this->getRepository('BoAdminBundle:Contracts')->find($oSubstitution->getIdcontract());
		}
		if($oGroup!=null and $oEmployee!=null){
			$aAgenda = $this->getRepository('BoAdminBundle:Agenda')->getByEmployeeAndGroup($oGroup,$oEmployee,$oDate);
		}
		if($oContract!=null and $oEmployee!=null){
			$aAgenda = $this->getRepository('BoAdminBundle:Agenda')->getByEmployeeAndContract($oContract,$oEmployee,$oDate);
		}
		if(count($aAgenda)>0){
			return $aAgenda;
		}
		return null;
	}
	//Return agenda object for the contracts and employee given in parameter
	public function getAgendaByHolder($idHolder,$oSubstitution,$oContract,$oGroup=null){
		if($idHolder==null or $idHolder<0) return null;
		$oRep = $this->getRepository('BoAdminBundle:Agenda');
		$oEmployee = $this->getEmployeeById($idHolder);
		if($oGroup!=null){
			$aAgenda = $oRep->getByEmployeeAndGroup($oGroup,$oEmployee,$oSubstitution->getStartdate());
			if(count($aAgenda)==1) return $aAgenda[0];
		}
		if(!$oContract) return null;
		if($oContract->getGroup()!=null and $oContract->getGroup() instanceof Group){
			$aAgenda = $oRep->getByEmployeeAndGroup($oContract->getGroup(),$oEmployee,$oSubstitution->getStartdate());
			if(count($aAgenda)==1) return $aAgenda[0];
		}
		$aAgenda = $oRep->getByEmployeeAndContract($oContract,$oEmployee,$oSubstitution->getStartdate());
		if(count($aAgenda)==1) return $aAgenda[0];
		return null;
	}
	private function getSubstByOption($oSubstitution,$oSchedule,$oDate,$option,$idsubstitute=null){
		if($idsubstitute==null) $oEmployee = $oSchedule->getEmployee();
		else $oEmployee = $this->getEmployeeById($idsubstitute);
		$hour = $oSubstitution->getHour();
		if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE) return $this->getAmDaySubstitution($hour,$oEmployee,$oSubstitution,$oDate);
		if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO) return $this->getPmDaySubstitution($hour,$oEmployee,$oSubstitution,$oDate);
		return null;
	}
	private function getSubstByOptionBis($oSubstitution,$oEmployee,$oDate,$option){
		if($oEmployee==null) $oEmployee = $this->getSubstituteBy($oSubstitution);
		$hour = $oSubstitution->getHour();
		if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE) return $this->getAmDaySubstitution($hour,$oEmployee,$oSubstitution,$oDate);
		if($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO) return $this->getPmDaySubstitution($hour,$oEmployee,$oSubstitution,$oDate);
		return null;
	}
	private function getHolderBy($oSubstitution){
		return  $this->getEmployeeById($oSubstitution->getIdholder());	
	}
	private function getSubstituteBy($oSubstitution){
		return $this->getEmployeeById($oSubstitution->getIdsubstitute());
	}
	private function getScheduleByOption($hour,$oSchedule,$oDate,$option){
		//this employee is holder of a constract and is substituted by another teacher
		$oSubsHolder = $this->getSubstBySchedule($oSchedule,$oDate,$option);
		$sSubstitution = $oSubsHolder!=null?$this->getStatusSubstitution($oSubsHolder):null;
		$sStatusAbsence = $this->getAbsenceForSchedule($oSchedule,$oDate,$option);
		$sStudentAbsence = $oSchedule->getContracts()?$this->getAbsenceForStudent($oSchedule->getContracts(),$oDate,$option):null;
		if($option == self::OPTION_DAY_ONE){
			$aResult = $this->getAmDaySchedule($hour,$oSchedule->getEmployee(),$oSchedule,$sStatusAbsence,$oDate,$sStudentAbsence,$sSubstitution);
			return $aResult;
		}else{	
			$aResult = $this->getPmDaySchedule($hour,$oSchedule->getEmployee(),$oSchedule,$sStatusAbsence,$oDate,$sStudentAbsence,$sSubstitution);
			return $aResult;
		}
	}
	private function getStatusSubstitution($oSubstitution){
		$oSubstitutte = $this->getEmployeeById($oSubstitution->getIdsubstitute());
		$oHolder =  $this->getEmployeeById($oSubstitution->getIdholder());
		return $oSubstitutte->getFirstname()." ".$oSubstitutte->getName().$this->transReplace().$oHolder->getFirstname()." ".$oHolder->getName();
	}
	private function getDaySubstitution($aSubstitution,$oSchedule,$employee,$oDate,$option){
		$aResult = array();
		$aSubstitution = $this->existDaySubstitution($aSubstitution,$option,$employee->getId());
		foreach($aSubstitution as $oSubstitution){
			if($oSubstitution->getStartdate()>=$oSchedule->getStartdate() and $oSubstitution->getEnddate()<=$oSchedule->getEnddate()){
				$hour = $oSubstitution->getHour();
				$daySubEvent = $option == self::OPTION_DAY_ONE?$this->getAmDaySubstitution($hour,$oEmployee,$oSubstitution,$oDate):$this->getPmDaySubstitution($hour,$oEmployee,$oSubstitution,$oDate);
			 	return $daySubEvent;
			}
		}
		return null;
	}
	public function getMonthSheetDays(\DateTime $date)
	{
		$first_day = DateTransformer::toMonday(DateTransformer::toFirstMonthDay($date));
		$last_day = DateTransformer::toSunday(DateTransformer::toLastMonthDay($date));

		return DateTransformer::getAllDaysBetween($first_day, $last_day);
	}
	public function getWeekSheetDays(\DateTime $date)
	{
		$first_day = DateTransformer::toMonday($date);
		$last_day = DateTransformer::toSunday($date);

		return DateTransformer::getAllDaysBetween($first_day, $last_day);
	}
	public function getWeekDayLimit(\DateTime $date){
		$weekday = $this->getWeekSheetDays($date);
		unset($weekday[5]);
		unset($weekday[6]);
		return $weekday;
	}
	public function getNextWeekDay($aWeek){
		$date = $aWeek[0];
		return DateTransformer::nextWeek($date);
	}
	public function getPreviousWeekDay($aWeek){
		$date = $aWeek[0];
		return DateTransformer::previousWeek($date);
	}
	public function getNextWeek($aWeek){
		$nextWeekDay = $this->getNextWeekDay($aWeek);
		return $this->getWeekSheetDays($nextWeekDay);
	}
	public function getPreviousWeek($aWeek){
		$PreviousWeekDay = $this->getPreviousWeekDay($aWeek);
		return $this->getWeekSheetDays($PreviousWeekDay);
	}
	public function getAbsenceByAgenda($oAgenda,$oDate,$option){
		return $this->getRepository('BoAdminBundle:Absences')->getByAgendaAndDate($oAgenda,$oDate);
	}
	public function getSubsByAgenda($oAgenda,$oDate,$option){
		return $this->getRepository('BoAdminBundle:Substitution')->getByAgendaAndDate($oAgenda,$oDate);
	}
	//Set student to substitution
	public function setStudentForSubstitution($substitution,$oContract,$oAgenda){
		//if the contract is group contract
		if($this->isGroupContract($oContract)==true and $oContract->getGroup()){
			$oGroup=$oContract->getGroup();
			$substitution->setStudent($oGroup->getName());
			$substitution->setIdgroup($oGroup->getId()); 				
		}else{
			$substitution->setIdcontract($oContract->getId());
			$substitution->setStudent($this->getStudentBy($oContract));
		}
		if($oAgenda) $substitution->setAgenda($oAgenda);
		$this->updateEntity($substitution);
		return $substitution;
	}
	protected function getExistInvite($aInvitation){
		foreach($aInvitation as $oInvitation){
			if($oInvitation->getStatus()==0) return true;
		}
		return false;
	}
	protected function getTeacherAbsForAdvisor($advisor){
		$aResult = array();
		$oRepAbs = $this->getRepository('BoAdminBundle:Absences');
		$aAbsence = $oRepAbs->getEmployeeDayAbsence(); 
		foreach($aAbsence as $oAbsence){
			$aContracts = $oAbsence->getContracts();
			$aContracts = $oAbsence->getGroup();
			foreach($aContracts as $oContract){
				if($oContract->getAdvisor()==$advisor){
					$aResult[] = $oAbsence; 
				}
			}
		}
		return $aResult;
	}
	protected function getStudentAbsForAdvisor($advisor){
		$aResult = array();
		$oRepAbs = $this->getRepository('BoAdminBundle:Absences');
		$aAbsence = $oRepAbs->getStudentsDayAbsence(); 
		foreach($aAbsence as $oAbsence){
			$aContracts = $oAbsence->getContracts();
			foreach($aContracts as $oContract){
				if($oContract->getAdvisor()==$advisor){
					$aResult[] = $oAbsence; 
				}
			}
		}
		return $aResult;
	}
	protected function getContent($oContract,$oGroup,$oSchedule,$aMailHead,$oEmployee){
		return $this->renderView("BoAdminBundle:Invitation:content.html.twig", array('contract'=>$oContract,'group'=>$oGroup,'agenda'=>$oSchedule,'studentname'=>$this->getStudentNameBy($oContract,$oGroup),'mailhead'=>$aMailHead->getMessageen(),'employee'=>$oEmployee));

	}
	protected function getContractsByAgenda($oAgenda){
		$oGroup = $oAgenda->getGroup();
		if($oGroup and $aContract=$oGroup->getContracts()){
			return $aContract[0];
		}
		return $oAgenda->getContracts();
	}
	//verify if the teacher is absent, return a boolean 1 if he's absent and 0 else
	protected function verifyAbsenceTeacher($substitution){
		$iHolder = $substitution->getIdholder();
		$oEmployee = $this->getEmployeeById($iHolder);
		$startdate = $substitution->getStartdate();
		$enddate = $substitution->getEnddate();
		$oRepAbs = $this->getRepository('BoAdminBundle:Absences');
		if($oEmployee){
			$aEmployeeAbsence = $oRepAbs->getEmployeeAbsencesByDates($oEmployee,$startdate,$enddate);
			if(count($aEmployeeAbsence)==0) return 0; 
		}
		if(count($aEmployeeAbsence)==1 and $oAbsence = $aEmployeeAbsence[0]){
			return $this->checkAmAndPm($substitution,$oAbsence);
		}
		foreach($aEmployeeAbsence as $oAbsence){
			$check = $this->checkAmAndPm($substitution,$oAbsence);
			if($check == 1) return $check;
		}
		return 0;
	}
	//verify if the student is absent, return a boolean 1 if he's absent and 0 else
	protected function verifyAbsenceStudent($substitution,$oAgenda){
		$aStudents = $this->getStudentsByAgenda($oAgenda);
		$startdate = $substitution->getStartdate();
		$enddate = $substitution->getEnddate();
		$oRepAbs = $this->getRepository('BoAdminBundle:Absences');
		if(count($aStudents)==1 and $ostudent=$aStudents[0]){
			$aStudentAbs = $oRepAbs->getStudentsAbsencesByDates($ostudent,$startdate,$enddate);
			if(count($aStudentAbs)==0) return 0; 
			elseif(count($aStudentAbs)==1 and $oAbsence = $aStudentAbs[0]) return $this->checkAmAndPm($substitution,$oAbsence);
			foreach($aStudentAbs as $oAbsence){
				$check = $this->checkAmAndPm($substitution,$oAbsence);
				if($check == 1) return $check;
			}
		}
		return 0;
	}
	public function checkAmAndPm($substitution){
		if($oAbsence->getAmorpm()== PERIOD_DAY_ALL) return 1;
		$ham = floatval($this->getRealAmHour($substitution)); 
		$hpm = floatval($this->getRealPmHour($substitution));
		if($ham>0 && $oAbsence->getAmorpm() == self::PERIOD_DAY_AM) return 1; 
		if($hpm>0 && $oAbsence->getAmorpm()== self::PERIOD_DAY_PM) return 1; 
		return 0;
	}
	//return 1 if verifyAbsenceTeacher=1 and verifyAbsenceStudent=1
	protected function verifyAbsencefor($substitution,$oAgenda){
		$bTeacherAbsence = $this->verifyAbsenceTeacher($substitution);
		$bStudentAbsence = $this->verifyAbsenceStudent($substitution,$oAgenda);
		if($bTeacherAbsence==1 and $bStudentAbsence==0) return 1;
		return 0; 
	}
	protected function getStudentsByAgenda($oAgenda){
		if($oAgenda->getGroup()!=null) return $this->getStudentByGroup($oAgenda->getGroup());
		$oContract = $oAgenda->getContracts();
		return $oContract->getStudents();
	}
	protected function getListHolders($aTeacher){
		$aResult = array();
		foreach($aTeacher as $oTeacher){
			$oRepSub = $this->getRepository('BoAdminBundle:Substitution');
			$aSubst = $oRepSub->findBy(array('idholder'=>$oTeacher->getId())); 
			if(count($aSubst)>0) $aResult[] = $oTeacher;
		}
		return $aResult;
	}
	protected function getListSubstitute($aTeacher){
		$aResult = array();
		foreach($aTeacher as $oTeacher){
			$oRepSub = $this->getRepository('BoAdminBundle:Substitution');
			$aSubst = $oRepSub->findBy(array('idsubstitute'=>$oTeacher->getId())); 
			if(count($aSubst)>0) $aResult[] = $oTeacher;
		}
		return $aResult;
	}
	protected function generateEventFor($oEvaluation){
		$oAgenda = new Agenda();
		$oAgenda->setStartdate($oEvaluation->getEvaldate());
		$oAgenda->setEnddate($oEvaluation->getEvaldate());
		if($this->getRealTime($oEvaluation->getEndtime())<=12){
			$oAgenda->setStartAm($oEvaluation->getEvaltime());
			$oAgenda->setEndAm($oEvaluation->getEndtime());
			$oAgenda->setStartPm($this->getTime());
			$oAgenda->setEndPm($this->getTime());
			$oAgenda->setAmorpm("AM");
		}elseif($this->getRealTime($oEvaluation->getEndtime())>12){
			$oAgenda->setStartAm($this->getTime());
			$oAgenda->setEndAm($this->getTime());
			$oAgenda->setStartPm($oEvaluation->getEvaltime());
			$oAgenda->setEndPm($oEvaluation->getEndtime());
			$oAgenda->setAmorpm("PM");
		}else return null;
		$oAgenda = $this->actualizeScheduleDay($oAgenda,$oEvaluation->getEvaldate());
		$oAgenda->setEmployee($oEvaluation->getEvaluator());
		$oAgenda->setHourperday($oEvaluation->getDuration());
		$oAgenda->setDescription($this->getDescriptionFor($oAgenda,$oEvaluation));
		$this->updateEntity($oAgenda);
		return $oAgenda;
	}
	private function getDescriptionFor($oAgenda,$oEvaluation){
		$oEmployee = $oAgenda->getEmployee();
		$sEventLib = $oEmployee->getFirstname()." ".$oEmployee->getName().$this->getEvalAction($oEvaluation->getEvaldate()).$oEvaluation->getFirstname()." ".$oEvaluation->getName().$this->getEvalLanguage($oEvaluation);
		if($this->getEvalCampus($oEvaluation)!=null) $sEventLib = $sEventLib.$this->getEvalCampus($oEvaluation);	
		$sEventLib = $sEventLib.", eval. id : ".$oEvaluation->getId().", eval. type : ".$oEvaluation->getEvaltype();	
		return $sEventLib;
	}
	private function getEvalAction($oDate){
		$lang = $this->getLang();
		$label = $lang=="en"?" teach ":" enseigne ";
		if($oDate!=null){
			if($oDate==$this->getToday()){
				$label = $lang=="en"?" evaluate ":" évalue ";
			}elseif($oDate<$this->getToday()){
				$label = $lang=="en"?" evaluated ":" a évalué ";
			}elseif($oDate>$this->getToday()){
				$label = $lang=="en"?" will evaluate ":" evaluera ";
			}
		}
		return $label; 

	}
	private function getEvalCampus($oEvaluation){
		if($oEvaluation->getCampus()!=null){
			return $this->getTransat().$oEvaluation->getCampus();
		}
		return null;
	}
	private function getEvalLanguage($oEvaluation){
		$lang = $this->getLang();
		$label = $lang=="en"?", in ":", en ";
		$language = $this->get('translator')->trans($oEvaluation->getLanguages());
		return $label.$language.", "; 
	}
	//getting the action by the referer, return the last word of the url
	protected function getAction($referer){
		$aReferer = explode('/',$referer);
		if($aReferer[count($aReferer)-1]!=null) return $aReferer[count($aReferer)-1];
		return $aReferer[count($aReferer)-2];
	}
	//Verify if the day is correct with date
	protected function actualize($oAgenda){
		if($oAgenda->getStartdate()!=$oAgenda->getEnddate()) return $oAgenda;
		return $this->actualizeScheduleDay($oAgenda,$oAgenda->getStartdate());
	}
	//Get form beetwen with dates 	
	protected function getCCdateForm(){
		$aCurrentWeek = $this->getWeekSheetDays($this->getToday());
		$oCcdate = new Ccdate($aCurrentWeek[0],$aCurrentWeek[4]); 
        	$form = $this->createForm('App\Form\CcdateType', $oCcdate);
		return $form->createView(); 
	}
	protected function logContractHisto($sTexte,$idcontract,$user){
		$oHcontract = new Histocontract();
		$oHcontract->setIdcontracts($idcontract);
		$oHcontract->setDescription($sTexte);
		$oHcontract->setUser($user);
		$this->updateEntity($oHcontract);
	}
	protected function compareThem($oldContract,$contract){
		$aResult = array();
		if($oldContract==null or $contract==null) return $aResult;
		if($oldContract->getReference()!=$contract->getReference()){
			$aResult[]=array('field'=>"Reference",'old'=>$oldContract->getReference(),"new"=>$contract->getReference());
		}
		if($oldContract->getTypeoftraining()!=$contract->getTypeoftraining()){
			$aResult[]=array('field'=>"Type of training",'old'=>$oldContract->getTypeoftraining(),"new"=>$contract->getTypeoftraining());
		}
		if($oldContract->getAdvisor()!=$contract->getAdvisor()){
			$old = $oldContract->getAdvisor()?$this->getFullNameOf($oldContract->getAdvisor()):null;
			$new = $contract->getAdvisor()?$this->getFullNameOf($contract->getAdvisor()):null;
			$aResult[]=array('field'=>"Advisor",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getContractual()!=$contract->getContractual()){
			$old = $oldContract->getContractual()?$oldContract->getContractual()->getName():null;
			$new = $contract->getContractual()?$contract->getContractual()->getName():null;
			$aResult[]=array('field'=>"Contractual",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getDepartment()!=$contract->getDepartment()){
			$aResult[]=array('field'=>"Department",'old'=>$oldContract->getDepartment(),"new"=>$contract->getDepartment());
		}
		if($oldContract->getSession()!=$contract->getSession()){
			$aResult[]=array('field'=>"Session",'old'=>$oldContract->getSession(),"new"=>$contract->getSession());
		}
		if($oldContract->getProgram()!=$contract->getProgram()){
			$aResult[]=array('field'=>"Program",'old'=>$oldContract->getProgram(),"new"=>$contract->getProgram());
		}
		if($oldContract->getStep()!=$contract->getStep()){
			$aResult[]=array('field'=>"Step",'old'=>$oldContract->getStep(),"new"=>$contract->getStep());
		}
		if($oldContract->getRyacc()!=$contract->getRyacc()){
			$aResult[]=array('field'=>"Ryacc",'old'=>$oldContract->getRyacc(),"new"=>$contract->getRyacc());
		}
		if($oldContract->getStartlesson()!=$contract->getStartlesson()){
			$aResult[]=array('field'=>"Start lesson",'old'=>$oldContract->getStartlesson(),"new"=>$contract->getStartlesson());
		}
		if($oldContract->getLearningplan()!=$contract->getLearningplan()){
			$aResult[]=array('field'=>"Learning plan",'old'=>$oldContract->getLearningplan(),"new"=>$contract->getLearningplan());
		}
		if($oldContract->getPlanreceived()!=$contract->getLearningplan()){
			$aResult[]=array('field'=>"Learning plan",'old'=>$oldContract->getLearningplan(),"new"=>$contract->getLearningplan());
		}
		if($oldContract->getTargetlevel()!=$contract->getTargetlevel()){
			$aResult[]=array('field'=>"Target level",'old'=>$oldContract->getTargetlevel(),"new"=>$contract->getTargetlevel());
		}

		if($oldContract->getHourperweek()!=$contract->getHourperweek()){
			$aResult[]=array('field'=>"Hour per week",'old'=>$oldContract->getHourperweek(),"new"=>$contract->getHourperweek());
		}
		if($oldContract->getLanguage()!=$contract->getLanguage()){
			$aResult[]=array('field'=>"Language",'old'=>$oldContract->getLanguage(),"new"=>$contract->getLanguage());
		}
		if($oldContract->getCoordinator()!=$contract->getCoordinator()){
			$old = $oldContract->getCoordinator()?$oldContract->getCoordinator()->getName():null;
			$new = $contract->getCoordinator()?$contract->getCoordinator()->getName():null;
			$aResult[]=array('field'=>"Coordinator",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getOrdernumber()!=$contract->getOrdernumber()){
			$aResult[]=array('field'=>"Order number",'old'=>$oldContract->getOrdernumber(),"new"=>$contract->getOrdernumber());
		}
		if($oldContract->getContractnumber()!=$contract->getContractnumber()){
			$aResult[]=array('field'=>"Contract number",'old'=>$oldContract->getContractnumber(),"new"=>$contract->getContractnumber());
		}
		if($oldContract->getMethodofsupply()!=$contract->getMethodofsupply()){
			$aResult[]=array('field'=>"Method of supply",'old'=>$oldContract->getMethodofsupply(),"new"=>$contract->getMethodofsupply());
		}
		if($oldContract->getContractdate()!=$contract->getContractdate()){
			$old = $oldContract->getContractdate()?$oldContract->getContractdate()->format("Y-m-d"):null;
			$new = $contract->getContractdate()?$contract->getContractdate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Contract date",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getClicsignaturedate()!=$contract->getClicsignaturedate()){
			$old = $oldContract->getClicsignaturedate()?$oldContract->getClicsignaturedate()->format("Y-m-d"):null;
			$new = $contract->getClicsignaturedate()?$contract->getClicsignaturedate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"L2L signature date",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getClicsignaturedate()!=$contract->getClicsignaturedate()){
			$old = $oldContract->getClicsignaturedate()?$oldContract->getClicsignaturedate()->format("Y-m-d"):null;
			$new = $contract->getClicsignaturedate()?$contract->getClicsignaturedate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"L2L signature date",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getAdresse()!=$contract->getAdresse()){
			$aResult[]=array('field'=>"Adresse",'old'=>$oldContract->getAdresse(),"new"=>$contract->getAdresse());
		}
		if($oldContract->getLocation()!=$contract->getLocation()){
			$aResult[]=array('field'=>"Location",'old'=>$oldContract->getLocation(),"new"=>$contract->getLocation());
		}
		if($oldContract->getSchedule()!=$contract->getSchedule()){
			$aResult[]=array('field'=>"Schedule",'old'=>$oldContract->getSchedule(),"new"=>$contract->getSchedule());
		}
		if($oldContract->getCampus()!=$contract->getCampus()){
			$aResult[]=array('field'=>"Campus",'old'=>$oldContract->getCampus(),"new"=>$contract->getCampus());
		}
		if($oldContract->getHourlyrate()!=$contract->getHourlyrate()){
			$aResult[]=array('field'=>"Hourly rate",'old'=>$oldContract->getHourlyrate(),"new"=>$contract->getHourlyrate());
		}
		if($oldContract->getTypecontract()!=$contract->getTypecontract()){
			$old = $oldContract->getTypecontract()?$oldContract->getTypecontract()->getReference():null;
			$new = $contract->getTypecontract()?$contract->getTypecontract()->getReference():null;
			$aResult[]=array('field'=>"Coordinator",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getGroup()!=$contract->getGroup()){
			$old = $oldContract->getGroup()?$oldContract->getGroup()->getName():null;
			$new = $contract->getGroup()?$contract->getGroup()->getName():null;
			$aResult[]=array('field'=>"Group",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getWorkfields()!=$contract->getWorkfields()){
			$old = $oldContract->getWorkfields()?$oldContract->getWorkfields()->getWfname():null;
			$new = $contract->getWorkfields()?$contract->getWorkfields()->getWfname():null;
			$aResult[]=array('field'=>"Workfields",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getStartrate()!=$contract->getStartrate()){
			$old = $oldContract->getStartrate()?$oldContract->getStartrate()->format("Y-m-d"):null;
			$new = $contract->getStartrate()?$contract->getStartrate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Start rate",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getHoursnumber()!=$contract->getHoursnumber()){
			$aResult[]=array('field'=>"Hours number",'old'=>$oldContract->getHoursnumber(),"new"=>$contract->getHoursnumber());
		}
		if($oldContract->getEstimatedcost()!=$contract->getEstimatedcost()){
			$aResult[]=array('field'=>"Estimated cost",'old'=>$oldContract->getEstimatedcost(),"new"=>$contract->getEstimatedcost());
		}
		if($oldContract->getTotalhours()!=$contract->getTotalhours()){
			$aResult[]=array('field'=>"Total hours",'old'=>$oldContract->getTotalhours(),"new"=>$contract->getTotalhours());
		}
		if($oldContract->getStartrate1()!=$contract->getStartrate1()){
			$old = $oldContract->getStartrate1()?$oldContract->getStartrate1()->format("Y-m-d"):null;
			$new = $contract->getStartrate1()?$contract->getStartrate1()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Start rate 1",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getHoursnumber1()!=$contract->getHoursnumber1()){
			$aResult[]=array('field'=>"Hours number 1",'old'=>$oldContract->getHoursnumber1(),"new"=>$contract->getHoursnumber1());
		}
		if($oldContract->getEstimatedcost1()!=$contract->getEstimatedcost1()){
			$aResult[]=array('field'=>"Estimated cost 1",'old'=>$oldContract->getEstimatedcost1(),"new"=>$contract->getEstimatedcost1());
		}
		if($oldContract->getStartrate()!=$contract->getStartrate()){
			$old = $oldContract->getStartrate()?$oldContract->getStartrate()->format("Y-m-d"):null;
			$new = $contract->getStartrate()?$contract->getStartrate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Start rate",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getHoursnumber()!=$contract->getHoursnumber()){
			$aResult[]=array('field'=>"Hours number",'old'=>$oldContract->getHoursnumber(),"new"=>$contract->getHoursnumber());
		}
		if($oldContract->getEstimatedcost()!=$contract->getEstimatedcost()){
			$aResult[]=array('field'=>"Estimated cost",'old'=>$oldContract->getEstimatedcost(),"new"=>$contract->getEstimatedcost());
		}
		if($oldContract->getTotalhours()!=$contract->getTotalhours()){
			$aResult[]=array('field'=>"Total hours",'old'=>$oldContract->getTotalhours(),"new"=>$contract->getTotalhours());
		}
		if($oldContract->getStartrate1()!=$contract->getStartrate1()){
			$old = $oldContract->getStartrate1()?$oldContract->getStartrate1()->format("Y-m-d"):null;
			$new = $contract->getStartrate1()?$contract->getStartrate1()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Start rate 1",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getHoursnumber1()!=$contract->getHoursnumber1()){
			$aResult[]=array('field'=>"Hours number 1",'old'=>$oldContract->getHoursnumber1(),"new"=>$contract->getHoursnumber1());
		}
		if($oldContract->getEstimatedcost1()!=$contract->getEstimatedcost1()){
			$aResult[]=array('field'=>"Estimated cost 1",'old'=>$oldContract->getEstimatedcost1(),"new"=>$contract->getEstimatedcost1());
		}
		if($oldContract->getStartrate2()!=$contract->getStartrate2()){
			$old = $oldContract->getStartrate2()?$oldContract->getStartrate2()->format("Y-m-d"):null;
			$new = $contract->getStartrate2()?$contract->getStartrate2()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Start rate 2",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getHoursnumber2()!=$contract->getHoursnumber2()){
			$aResult[]=array('field'=>"Hours number 2",'old'=>$oldContract->getHoursnumber2(),"new"=>$contract->getHoursnumber2());
		}
		if($oldContract->getEstimatedcost2()!=$contract->getEstimatedcost2()){
			$aResult[]=array('field'=>"Estimated cost 2",'old'=>$oldContract->getEstimatedcost2(),"new"=>$contract->getEstimatedcost2());
		}
		if($oldContract->getInitialhours()!=$contract->getInitialhours()){
			$aResult[]=array('field'=>"Initial hours",'old'=>$oldContract->getInitialhours(),"new"=>$contract->getInitialhours());
		}
		if($oldContract->getUsedhours()!=$contract->getUsedhours()){
			$aResult[]=array('field'=>"Used hours",'old'=>$oldContract->getUsedhours(),"new"=>$contract->getUsedhours());
		}
		if($oldContract->getTypetime()!=$contract->getTypetime()){
			$aResult[]=array('field'=>"Type time",'old'=>$oldContract->getTypetime(),"new"=>$contract->getTypetime());
		}
		if($oldContract->getNotes()!=$contract->getNotes()){
			$aResult[]=array('field'=>"Notes",'old'=>$oldContract->getNotes(),"new"=>$contract->getNotes());
		}
		if($oldContract->getConfirmation()!=$contract->getConfirmation()){
			$aResult[]=array('field'=>"Confirmation",'old'=>$oldContract->getConfirmation(),"new"=>$contract->getConfirmation());
		}
		if($oldContract->getStartdate()!=$contract->getStartdate()){
			$old = $oldContract->getStartdate()?$oldContract->getStartdate()->format("Y-m-d"):null;
			$new = $contract->getStartdate()?$contract->getStartdate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Start date",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getEnddate()!=$contract->getEnddate()){
			$old = $oldContract->getEnddate()?$oldContract->getEnddate()->format("Y-m-d"):null;
			$new = $contract->getEnddate()?$contract->getEnddate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"End date",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getReceivedate()!=$contract->getReceivedate()){
			$old = $oldContract->getReceivedate()?$oldContract->getReceivedate()->format("Y-m-d"):null;
			$new = $contract->getReceivedate()?$contract->getReceivedate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Receive date",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getInitialenddate()!=$contract->getInitialenddate()){
			$old = $oldContract->getInitialenddate()?$oldContract->getInitialenddate()->format("Y-m-d"):null;
			$new = $contract->getInitialenddate()?$contract->getInitialenddate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Initial enddate",'old'=>$old,"new"=>$new);
		}
		if($oldContract->getRenddate()!=$contract->getRenddate()){
			$old = $oldContract->getRenddate()?$oldContract->getRenddate()->format("Y-m-d"):null;
			$new = $contract->getRenddate()?$contract->getRenddate()->format("Y-m-d"):null;
			$aResult[]=array('field'=>"Initial enddate",'old'=>$old,"new"=>$new);
		}
		return $aResult;
	}
	protected function logChange($oldContract,$contract){
		$sActivity="Contract Modification: ";
		$aDiff = $this->compareThem($oldContract,$contract);
		foreach($aDiff as $aTab){
			$sActivity = $sActivity.$aTab['field']." old->".$aTab['old']." new->".$aTab['new'].", ";
		}
		$this->logContractHisto($sActivity,$contract->getId(),$this->getConnected());
	}
	/*
	* Get the advisor email by the schedule
	*/
	protected function getAdvisorEmail($agenda){
		$oContract = $this->getContractsBySchedule($agenda);
		if($oContract and $oContract->getAdvisor()) return $oContract->getAdvisor()->getEmail();
		$oGroup = $this->getGroupByScheduleBis($agenda);
		if($oGroup and $oGroup->getAdvisor()) return $group->getAdvisor()->getEmail();
		return null;
	}
	/*
	* Notify advisor about of modfication of teacher schedule
	*/
	protected function notifyAdminMembers($agenda){
		$res = null;
		$advisor_email = null;
		$advisor_email = $this->getAdvisorEmail($agenda);
		$cc = $this->getRepository('BoAdminBundle:Param')->getParam("email_notif_change_schedule",31);
		
		if($agenda->getContracts() and $agenda->getContracts()->getAdvisor()){ 
			$oAdvisor = $agenda->getContracts()->getAdvisor();
			$fullname = $oAdvisor->getFirstname();
		}elseif($agenda->getGroup() and $agenda->getGroup()->getAdvisor()){
			$oAdvisor = $agenda->getGroup()->getAdvisor();
			$fullname = $oAdvisor->getFirstname();
		}
		$subject = "Modification of schedule";
		$Mfe = $this->getRepository('BoAdminBundle:Param')->getParam("notification_message_footer",48);
		$Bci = $this->getRepository('BoAdminBundle:Param')->getParam("email_notification_superadmin",47);
		$body = $this->renderView("BoAdminBundle:Agenda:notification.html.twig", array('agenda'=>$agenda,'user'=>$this->getConnectedUser(),'mfe'=>$Mfe));
		$res = $this->sendmail($advisor_email,$subject,$body,$cc,$Bci);
		return $res;			
	}
	/*
	* Notify advisor for a new substitution
	*/
	protected function notifyForSubstitution($oSubstitution,$agenda){
		$res = null;
		$advisor_email = null;
		$advisor_email = $this->getAdvisorEmail($agenda);
		$cc = $this->getRepository('BoAdminBundle:Param')->getParam("email_notif_change_schedule",31);
		
		if($agenda->getContracts() and $agenda->getContracts()->getAdvisor()){ 
			$oAdvisor = $agenda->getContracts()->getAdvisor();
			$fullname = $oAdvisor->getFirstname();
		}elseif($agenda->getGroup() and $agenda->getGroup()->getAdvisor()){
			$oAdvisor = $agenda->getGroup()->getAdvisor();
			$fullname = $oAdvisor->getFirstname();
		}
		$employee = $agenda->getEmployee();
		$oHolder = $this->getEmployeeById($oSubstitution->getIdholder());
		$oSubstitute = $this->getEmployeeById($oSubstitution->getIdsubstitute());
		if($employee==$oHolder and $oSubstitute instanceof Employee){
			$subject = "New substitution / Nouveau remplacement";
			$Mfe = $this->getRepository('BoAdminBundle:Param')->getParam("notification_message_footer",48);
			$Bci = $this->getRepository('BoAdminBundle:Param')->getParam("email_notification_superadmin",47);
			$body = $this->renderView("BoAdminBundle:Substitution:notification.html.twig", array('agenda'=>$agenda,'substitution'=>$oSubstitution,'holder'=>$oHolder,'substitute'=>$oSubstitute,'user'=>$this->getConnectedUser(),'mfe'=>$Mfe));
			$res = $this->sendmail($advisor_email,$subject,$body,$cc,$Bci);
			return $res;
		}
		return null;			
	}
	protected function checkAbsenceDay($oAgenda,$absence){
		$startdate = $absence->getStartdate();
		$enddate = $absence->getEnddate();
		$bReturn = false;
		while($startdate<=$enddate){
			$bReturn = $this->checkDay($startdate,$oAgenda);
			if($bReturn==true) return $bReturn;
			$startdate = $this->getDatePlus($startdate,1);
		}
		return $bReturn;
	}
	protected function checkDay($ddate,$entity){
		return $this->existDayInEntity2($ddate->format("l"),$entity);
	}
	protected function createSubsContForm($absence){
		$aSubContform = array();
		$aContracts = $absence->getContracts();
		$oEmployee = $absence->getEmployee();
		foreach($aContracts as $oContract){
			$aAgenda = $this->getCurrentAgenda($oEmployee,$oContract);
			foreach($aAgenda as $oAgenda){
				//Get true if absence day exists in schedule days, false else
				$check = $this->checkAbsenceDay($oAgenda,$absence);
				if($check==true) $aSubContform[$oContract->getId()] = $this->getSubstitutionForm($oAgenda,$absence);
			}
		}
		return $aSubContform;
	}
	protected function getSubstitutionForm($oAgenda,$absence){
		//Check if there already exists a substitution for this absence 
		$oSubstitution = $this->checkSubsForAbs($absence,$oAgenda);
		//If $oSubstitution is null, so there does not exists a substitution for this absence then create
		if($oSubstitution==null){
			$oSubstitution = $this->initSubstitutionfor($oAgenda,$absence);
		}
		$oSubstitution = $this->updateDay($oSubstitution);
		$substitutionform = $this->createForm('App\Form\SubstitutionType2',$oSubstitution);
		return $substitutionform->createView();
	}
	protected function createSubsGroupForm($absence){
		$aSubGroupform = array();
		$aGroup = $absence->getGroup();
		$oEmployee = $absence->getEmployee();
		$oEmployee = $absence->getEmployee();
		foreach($aGroup as $oGroup){
			$aAgenda = $this->getCurrentAgenda($oEmployee,null,$oGroup);
			foreach($aAgenda as $oAgenda){
				//Get true if absence day exists in schedule days, false else
				$check = $this->checkAbsenceDay($oAgenda,$absence);
				if($check==true){ 
					$aSubGroupform[$oGroup->getId()] = $this->getSubstitutionForm($oAgenda,$absence);
				}
			}
		}
		return $aSubGroupform;
	}
	protected function getAbsenceFormView(){
		$oAbsence = new AbsEmp();
		$oAbsence->setNumberday(1);
        	$form = $this->createForm('App\Form\AbsencesType4',$oAbsence);		
		return $form->createView();
	}
	//Return an array of schedule array and substitution Array
	protected function getScheduleForTs($oEmployee,$oDate){
		$aResult = array();
		if($this->isAbsentEmployee($oEmployee,$oDate,3)==true) return null;
		//Get substitution for AM and PM
		$aSubsAM = $this->getSubsByEmployeeAndDate($oEmployee,$oDate,1);
		$aSubsPM = $this->getSubsByEmployeeAndDate($oEmployee,$oDate,2);
		//Get schedule for AM and PM
		$aSchAM = $this->getDaySchedule($employee,$oDate,1);
		$aSchPM = $this->getDaySchedule($employee,$oDate,2);
		//If there is no substitution
		if(count($aSubsAM)==0 and count($aSubsPM)==0){
			//if there exist one schedule for the am and one another for the pm
			if(count($aSchAM)==1 and count($aSchPM)==1){
 				$oSchAM = $aSchAM[0];
				$oSchPM = $aSchPM[0];
				//It's the same teacher for the both schedule so it's just one schedule for am and pm
				if($oSchAM == $oSchPM and $this->getStudentAbsBool($oSchPM,$oDate,1)==false){

				} 
				$aResult['sch'][] = $oSchPM;
			}else{
				if(count($aSchAM)>0){
					foreach($aSchAM as $oSchAM){
						$aResult['sch'][] = $oSchAM;
					}
				}	
				if(count($aSchPM)>0){
					foreach($aSchPM as $oSchPM){ 
						$aResult['sch'][] = $oSchPM;
					}
				}				
			}
		}elseif(count($aSubsAM)>0 and count($aSubsPM)==0){
			foreach($aSubsAM as $oSubsAM){
				$aResult['sub'][] = $oSubsAM;
			}
			foreach($aSchPM as $oSchPM){ 
				$aResult['sch'][] = $oSchPM;
			}
		}elseif(count($aSubsAM)==0 and count($aSubsPM)>0){
			foreach($aSchAM as $oSchAM){ 
				$aResult['sch'][] = $oSchPM;
			}
			foreach($aSubsAM as $oSubsAM){
				$aResult['sub'][] = $oSubsAM;
			}
		}

	}
	//if $param=1 then schedule, else $param=2 then substitution
	//if $option equal to 1 then it's a AM else 2 then PM
	//if $option equal to 3 then the timesheet will be for the whole day
	protected function initTs($schedule,$option,$param){
		$timesheet=new Timesheet();
		if($option == self::OPTION_DAY_ONE or $option == self::OPTION_DAY_THREE){
			$oStartam = $schedule->getStartam();
			$oEndam = $schedule->getEndam();
		}
		if($option == self::OPTION_DAY_TWO or $option == self::OPTION_DAY_THREE){
			$oStartpm = $schedule->getStartpm();
			$oEndpm = $schedule->getStartpm();
		}
		$sHour = $this->getHourBySchedule($schedule,$option,$param);
		$timesheet->setStartam($oStartam);
		$timesheet->setStartpm($oStartpm);
		$timesheet->setEndam($oEndam);
		$timesheet->setEndpm($oEndpm);	
		$timesheet->setHour($this->getRealTime($sHour));
		return $timesheet;		
	}
	//if option equal to 1 then it's a AM else 2 then PM
	//if $param=1 then schedule, else $param=2 then substitution
	private function getHourBySchedule($schedule,$option,$param){
		$hour = 0;
		if($option == self::OPTION_DAY_THREE and $this->getAmHour($schedule)>0 and $this->getAmHour($schedule)>0){
			$hour = $this->getHourByParam($param);
		}elseif($option == self::OPTION_DAY_ONE){
			if($this->getAmHour($schedule)>0 and $this->getAmHour($schedule)>0){
				$hour = $this->getAmHour($schedule);
			}elseif($this->getAmHour($schedule)>0){
				$hour = $this->getHourByParam($param);
			}
		}elseif($option == self::OPTION_DAY_TWO){
			if($this->getAmHour($schedule)>0 and $this->getAmHour($schedule)>0){
				$hour = $this->getPmHour($schedule);
			}elseif($this->getPmHour($schedule)>0){
				$hour = $this->getHourByParam($param);
			}
		}
		return $hour;
	}
	//if $param=1 then schedule, else $param=2 then substitution
	private function getHourByParam($param){
		if($param==null) $sHour = $schedule->getHourperday();
		else $sHour = $schedule->getHour();
	}
	//Return true if student is absence and false else
	private function getStudentAbsBool($oSchedule,$oDate,$param,$option=null){
		$oContract==null;
		if($oSchedule->getGroup()) return false;
		if($param==1){//case of schedule
			$oContract = $oSchedule->getContracts();
		}elseif($param==2){//case of Substitution
			if($oSchedule->getIdgroup()>0) return false;
			$oContract = $this->getContractById($oSchedule->getIdcontract());
		}
		//If contract equal to null or there is no contract return false		
		if($oContract==null) return false;
		//if there are many students on the contract then return false
		$aStudent = $oContract->getStudents();
		if(count($aStudent)!=1) return false;
		//When thare is one student on the contract then
		$oStudent = $aStudent[0];
		$aAbsence = $this->getStudentAbsByDate($oStudent,$oDate);
		if(isset($aAbsence[0]) and $oAbsence=$aAbsence[0]){
			if($oAbsence->getAmorpm()== PERIOD_DAY_ALL){
				return true;
			}
			if($oAbsence->getAmorpm() == self::PERIOD_DAY_AM and $option == self::OPTION_DAY_ONE){
				return true;
			}
			if($oAbsence->getAmorpm()== self::PERIOD_DAY_PM and $option == self::OPTION_DAY_TWO){
				return true;
			}
		}		
		return false;
	}
	protected function updateUserAccount($employee){
		//search user account by employee information and get it
		$aUser = $this->getRepository('BoUserBundle:User')->findBy(array('employee'=>$employee));
		if(!isset($aUser[0])) return false;
		$oUser = $aUser[0];
		//disable the employee account if active is true or enable it else 
		if($employee->getActive()==true){
			$oUser->setEnabled(true);			
		}else{
			$oUser->setEnabled(false);
		}
		return $this->updateEntity($oUser);
	}
	protected function getTicketForm(){
		$oTicket = new Tickets();
		$oTicketContact = $this->getRepository('BoAdminBundle:TicketContacts')->find(1);
		if($oTicketContact){
			$oTicket->setContacts($oTicketContact);
			$oTicket->setSubject($oTicketContact->getName());
		}
		return $oTicket;
	}
	protected function getTicketFormView(){
        	$form = $this->createForm('App\Form\HelpType', $this->getTicketForm());
		return $form->createView();
	}
	/*
	*@param integer $end
	*@param integer $start
	*@return array of interger
	*/
	protected function getIntArray($end,$start=null){
		if($start==null) $start=0;
		for($i=$start;$i<=$end;$i++){
			yield $i;
		}
	}
	/*
	*@param integer $end
	*@param integer $start
	*@return array of interger
	*/
	protected function getStrArray($end,$start=null){
		if($start==null) $start=0;
		for($i=$start;$i<=$end;$i++){
			yield $i;
		}
	}
	/*
	*@param string $name
	*@param string $eaddress
	*@return string
	*/
	protected function formatEmail($eaddress,$name=null){
		$newAddress = $name==null?"<".$eaddress.">":$name."<".$eaddress.">";
		return $newAddress;
	}
	protected function getRobotLastDay(){
		$aRobot = $this->getRepository('BoAdminBundle:Robot')->findBy(array(),array('id' => 'desc'),1,0);
		if(isset($aRobot[0])) return $aRobot[0]->getDdate();
		return null;
	}
	protected function getMonthlyhourBy($usedhour,$opt=0){
		$oMonthlyhour=null;
		$oRep = $this->getRepository('BoAdminBundle:Monthlyhour');
		$oContract = $usedhour->getContract();
		$oStudent = $usedhour->getStudent();
		$oGroup = $usedhour->getGroup();
		$sMonth = $usedhour->getDdate()->format("F");
		$sYear = $usedhour->getDdate()->format("Y");
		if($oGroup){
			$aMonthlyhour = $oRep->getByGroupAndDate($oGroup->getId(),$sMonth,$sYear);
			if(count($aMonthlyhour)==1) $oMonthlyhour=$aMonthlyhour[0];
		}
		if($oContract){
			$aMonthlyhour = $oRep->getByContractAndDate($oContract->getId(),$sMonth,$sYear);
			if(count($aMonthlyhour)==1) $oMonthlyhour=$aMonthlyhour[0];
		}
		if($oMonthlyhour){
			if($opt=0) $oMonthlyhour->setHour($oMonthlyhour->getHour()+$usedhour->getHour());
			else $oMonthlyhour->setHour($usedhour->getDtuh());
			$this->updateEntity($oMonthlyhour);
			return $oMonthlyhour;
		}
		return null;	
	}
	//get an object of Monthlyhour if it exists else return null
	protected function getMonthlyhourByContract($usedhour){
		$oRepMH = $this->getRepository('BoAdminBundle:Monthlyhour');
		$oContract = $usedhour->getContract();
		$oGroup = $usedhour->getGroup();
		$sMonth = $usedhour->getDdate()->format("F");
		$sYear = $usedhour->getDdate()->format("Y");
		if($oContract) $aMonthlyhour = $oRepMH->getByContractAndDate($oContract->getId(),$sMonth,$sYear);
		if($oGroup) $aMonthlyhour = $oRepMH->getByGroupAndDate($oGroup->getId(),$sMonth,$sYear);
		if(isset($aMonthlyhour) and isset($aMonthlyhour[0])) return $aMonthlyhour[0];
		return null;
	}
	protected function getTeacherName($sId){
		$aId = explode(",",$sId);
		$aRes = array();
		foreach($aId as $id){
			if($id=="Noshow") return $id;
			$oEmployee = $this->getRepository('BoAdminBundle:Employee')->find($id);
			if($oEmployee) $aRes[] = $oEmployee->getFirstname()." ".$oEmployee->getName(); 
		}
		return join(",",$aRes);
	}
	protected function updateBreak($oTC){
		$ham = floatval($this->getRealAmHour($oTC)); 
		$hpm = floatval($this->getRealPmHour($oTC)); 
		$hourperday = floatval($oTC->getHourperday());
		$total = floatval($ham+$hpm);  
		if($ham>0 and $hpm>0){
			if($total == $hourperday){
				$oTC->setBam(false);
				$oTC->setBpm(false);
			}else{
				if($ham<=3){
					$oTC->setBam(false);
				}else{
					$ham=floatval($ham)-0.25;
					$oTC->setBam(true);
				}
				if($hpm<=3){
					$oTC->setBpm(false);
				}else{
					$hpm=floatval($hpm)-0.25;
 					$oTC->setBpm(true);
				}
			}
			$oTC->setHam($ham);
			$oTC->setHpm($hpm);
		}elseif($ham>0){
			if($ham == $hourperday){
				$oTC->setBam(false);
			}else{ 
				$ham = $hourperday;
				$oTC->setBam(true);
			}
			$oTC->setBpm(false);
			$oTC->setHpm(0);
			$oTC->setHam($ham);
		}elseif($hpm>0){ 
			if($hpm == $hourperday){ 
				$oTC->setBpm(false);
			}else{
				$hpm = $hourperday;
				$oTC->setBpm(true);
			}
			$oTC->setBam(false);
			$oTC->setHam(0);
			$oTC->setHpm($hpm);
		}	
		return $oTC;
	}
	protected function setStatusFor($oEntity){
		if($oEntity->getEnddate()<$this->getToday()){
			$oEntity->setStatus(0);
		}elseif($oEntity->getStartdate()>$this->getToday()){
			$oEntity->setStatus(2);
		}else{
			$oEntity->setStatus(1);
		}
		$this->updateEntity($oEntity);
		return $oEntity;
	}
	protected function isBreakByOption($oSchedule,$option){
		if($option == self::OPTION_DAY_ONE and ($oSchedule->getBam()==1 or $oSchedule->getBam()==true)) return true;
		if($option == self::OPTION_DAY_TWO and ($oSchedule->getBpm()==1 or $oSchedule->getBpm()==true)) return true;
		return false;
	}
	protected function updateHours($oTC){
		$ham = floatval($this->getRealAmHour($oTC)); 
		$hpm = floatval($this->getRealPmHour($oTC)); 
		$hourperday = floatval($oTC->getHourperday());
		$total = floatval($ham+$hpm);  
		if($ham>0 and $hpm>0){
			if($total == $hourperday){
				$oTC->setBam(false);
				$oTC->setBpm(false);
			}else{
				$hamwb = floatval($this->getRealHourScheduled($oTC,1));
				$hpmwb = floatval($this->getRealHourScheduled($oTC,2));
				if($ham<=3){
					$oTC->setBam(false);
				}else{
					$ham=floatval($ham)-0.25;
					$oTC->setBam(true);
				}
				if($hpm<=3){
					$oTC->setBpm(false);
				}else{
					$hpm=floatval($hpm)-0.25;
 					$oTC->setBpm(true);
				}
			}
			$oTC->setHam($ham);
			$oTC->setHpm($hpm);
		}elseif($ham>0){
			if($ham == $hourperday){
				$oTC->setBam(false);
			}else{ 
				$ham = $hourperday;
				$oTC->setBam(true);
			}
			$oTC->setBpm(false);
			$oTC->setHpm(0);
			$oTC->setHam($ham);
		}elseif($hpm>0){ 
			if($hpm == $hourperday){ 
				$oTC->setBpm(false);
			}else{
				$hpm = $hourperday;
				$oTC->setBpm(true);
			}
			$oTC->setBam(false);
			$oTC->setHam(0);
			$oTC->setHpm($hpm);
		}
		$this->updateEntity($oTC);	
		return $oTC;
	}
	//Get value of break in minutes
	protected function getBreakValue(){
		$param = $this->getParam("agenda_break_value",40);
		return floatval($param)/60;
	}
	protected function getTeacherContracts($oEmployee){
		$numberday = $this->getRepository('BoAdminBundle:Param')->getParam("contracts_number_day",44);
		$limitdate = $this->getDatePlus($this->getToday(),-$numberday);
		$oEntity = null;
		$aSchedule = $this->getAgendaByEmployee($oEmployee,1);
		$aResult = array();
		foreach($aSchedule as $oSchedule){
			if($oSchedule->getGroup()!=null){
				$oEntity = $oSchedule->getGroup();
				$studentname = $this->getStudentNameBy(null,$oSchedule->getGroup());
				$option = 1;
			}elseif($oSchedule->getContracts()!=null and ($oSchedule->getContracts()->getStatus()==1 or $oSchedule->getContracts()->getEnddate()>$limitdate)){
				$oEntity = $oSchedule->getContracts();
				$option = 2;
				$studentname = $this->getStudentNameBy($oSchedule->getContracts(),null);
			} 
			if($oEntity!=null and !isset($aResult[$studentname])) $aResult[$studentname] =  array('option'=>$option,'id'=>$oEntity->getId(),'studentname'=>$studentname);
		}
		return $aResult;
	} 
	protected function getContractInfoFrom($request){
		$aRequest = $this->getFromRequestBis(array('idcontract'),$request);
		$option_id = $aRequest['idcontract'];
		return explode("_",$option_id);
	}
	protected function getStudentUsedHourBis($oContract,$startdate,$enddate){
		set_time_limit(15200);
		$aSUH = array();
		$aStudents = $oContract->getStudents();
		if(count($aStudents)==0) return null;
                foreach($aStudents as $oStudent){
                    $studentUsedHour = $this->getRangeStudentUsedHour($oStudent, $oContract, $startdate, $enddate);

                    $aSUH[$oContract->getId()][] = $studentUsedHour;
                }
		return $aSUH;
	}
        private function getRangeStudentUsedHour($oStudent, $oContract, $startdate, $enddate){   
            $aTab = array();
            while($startdate<=$enddate){
                $aDayUH = $this->getDayUsedhour($oContract,$oStudent,$startdate);
                $aTab[$oStudent->getId()][intval($startdate->format("d"))] = $aDayUH;
                $startdate = $this->getDatePlus($startdate,1);
            }             
            return $aTab;
        }
	protected function getGroupUsedHour($oGroup,$startdate,$enddate){
		set_time_limit(15200);
		$aSUH = array();
		$aContracts = $oGroup->getContracts();
		if(count($aContracts)==0) return array();
		foreach($aContracts as $oContract){
			//Get student object
			$aStudents = $oContract->getStudents();
			if(count($aStudents)==1 and isset($aStudents[0])) $oStudent = $aStudents[0];
			else $oStudent = null;
			//Initialize startdate for the contract
			$newstart = $startdate;
			while($newstart<=$enddate){
				$aDayUH = $this->getDayUsedhour($oContract,$oStudent,$newstart);
				$aSUH[$oContract->getId()][intval($newstart->format("d"))] = $aDayUH;
				$newstart = $this->getDatePlus($newstart,1);
			}
		}	
		return $aSUH;
	}
        
        //Get Used hour for for the contract which has many students: Group
	protected function getContractUsedHour($oContract,$startdate,$enddate){
            set_time_limit(15200);              
            $aSUH = array();
                
            //Initialize startdate for the contract
            $newstart = $startdate;
            while($newstart<=$enddate){
                $aDayUH = $this->getDayUsedhour($oContract,null,$newstart);
                $aSUH[$oContract->getId()][intval($newstart->format("d"))] = $aDayUH;
                $newstart = $this->getDatePlus($newstart,1);
            }	
            return $aSUH;
	}  
        
	//get student used hour
	public function getGroupUsedHourBy($oGroup,$oDate,$option){
		$hour = 0;
		$oRepAg = $this->getRepository('BoAdminBundle:Agenda');
		//Check absence for the student, if true return 0 else fale return 1
		$bGroupAbs = $this->isGroupAbsent2($oGroup,$oDate,$option);
		if($bGroupAbs==true) return $hour;
		if($this->isHolidays($oDate,$option)==true) return $hour;
		$aSubstitution = $this->getSubsByGroup($oGroup,$oDate,$option);
		if(count($aSubstitution)>0) return $this->sumHourBySubs($aSubstitution);
		$aSchedule = $this->getGroupDaySchedule($oGroup,$oDate,$option);
		$aSchedule = $this->getScheduleWithAbs($aSchedule,$oDate,$option);
		return $this->getSchUhBy($aSchedule,$option);
	}
	protected function getDayUsedhour($oContract,$oStudent,$oDate){
		$oGroup = $oContract->getGroup();
		$oRepUH = $this->getRepository('BoAdminBundle:Usedhour');
		$sHam = $sHpm = 0;
		//Getting the startdate
		$startdate = $oContract->getStartdate();
		$aUH = $oRepUH->getUhForContract($oDate,$oContract);
		$sLam = $oRepUH->getLegendForStudent($oStudent,$oContract,$oDate,1);
		$sLpm = $oRepUH->getLegendForStudent($oStudent,$oContract,$oDate,2);
		if($oGroup!=null and count($oGroup->getContracts())>1){ 
			$oRepSch = $this->getRepository('BoAdminBundle:Agenda');
			$aSchedule = $oRepSch->getByGroupDate($oGroup,$oDate);
			if(count($aSchedule)>0) $aSUH = $this->getGroupArrayHour($aSchedule[0],$oStudent,$oDate);
		}else $aSUH = $this->getStudentArrayHour($oContract,$oStudent,$oDate);
		if($this->isWeekend($oDate)==true){
			$sHam = $sHpm = 0;
			$sLam = $sLpm = "WE";
			return array('am'=>$sHam,'lam'=>$sLam,'pm'=>$sHpm,'lpm'=>$sLpm);
		}		
		if($startdate > $oDate){
			$sHam = $sLam = $sHpm = $sLpm = "X";
			return array('am'=>$sHam,'lam'=>$sLam,'pm'=>$sHpm,'lpm'=>$sLpm);
		}
		if(isset($aUH[0])){
			$sHam = $aUH[0]->getHam();
			$sHpm = $aUH[0]->getHpm();
		}elseif(isset($aSUH['am']) or isset($aSUH['pm'])){
			$sHam=$aSUH['am'];
			$sHpm=$aSUH['pm'];
		} 
		if($sHam==0 and $sLam=="P") $sLam = "-";
		if($sHpm==0 and $sLpm=="P") $sLpm = "-";
		return array('am'=>floatval($sHam),'lam'=>$sLam,'pm'=>floatval($sHpm),'lpm'=>$sLpm);
	}	
	protected function getTotalMonth($aUsedHour){
		$totalmonth=0;
		foreach($aUsedHour as $aTab){
			$totalmonth=$totalmonth+floatval($aTab['am'])+floatval($aTab['pm']);
		}
		return $totalmonth;
	}
	protected function getUhMonthHour($aUsedHour){
		$total = 0;
		foreach($aUsedHour as $oUsedHour){
			$total = $total + $oUsedHour->getHour();
		}
		return $total;
	}
	//get an object of Monthlyhour if it exists else return null
	protected function getMHByContract($idContract,$sMonth,$sYear){
		$oRepMH = $this->getRepository('BoAdminBundle:Monthlyhour');
		$aMonthlyhour = $oRepMH->getByContractAndDate($idContract,$sMonth,$sYear);
		if(isset($aMonthlyhour[0])) return $aMonthlyhour[0];
		return null;
	}
	protected function getMonthTotal($oUsedhour){
		$aUsedHour = array();
		$oContract = $oUsedhour->getContract();
		$oGroup = $oUsedhour->getGroup();
		$sMonth = $oUsedhour->getDdate()->format("F");
		$sYear = $oUsedhour->getDdate()->format("Y");
        	$oRepUH = $this->getRepository('BoAdminBundle:Usedhour');
		if($oContract!=null) $aUsedHour = $oRepUH->getByContract($oContract->getId(),$sMonth,$sYear);
		if($oGroup!=null) $aUsedHour = $oRepUH->getByContract($oGroup->getId(),$sMonth,$sYear);
		$fTotal = 0;
		foreach($aUsedHour as $oUsedHour){
			$fTotal = $fTotal + $oUsedHour->getHour();
		}
		return $fTotal;
	}
	protected function createMonthlyBy($oUsedhour){
		$oMonthlyhour = $this->getMonthlyhourByContract($oUsedhour);
		$fTotal = $this->getMonthTotal($oUsedhour);
		if($oMonthlyhour==null){
			$oMonthlyhour = new Monthlyhour();
			$oMonthlyhour->setContract($oUsedhour->getContract());
			$oMonthlyhour->setGroup($oUsedhour->getGroup());
			$oMonthlyhour->setStudent($oUsedhour->getStudent());
			$oMonthlyhour->setMonth($oUsedhour->getDdate()->format("F"));
			$oMonthlyhour->setYear($oUsedhour->getDdate()->format("Y"));	
		}
		$oMonthlyhour->setHour($fTotal);
		$this->updateEntity($oMonthlyhour);
		return $oUsedhour;
	}
	protected function isAddedAgenda($oGroup,$oAgenda){
		foreach($oGroup->getAgenda() as $oSchedule){
			if($oAgenda==$oSchedule) return true;
		}
		return false;
	}
	protected function addAgendaToGroup($oGroup,$oAgenda){
		if($this->isAddedAgenda($oGroup,$oAgenda)==true) return false;
		$oGroup->addAgenda($oAgenda);
		$res = $this->updateEntity($oGroup);
		return true;
	}
	protected function getTsDocForm(){
		$aPeriod = $this->getPeriodMonth(); 
		$oTsdoc = new Tsdoc();
		$oTsdoc->setStartdate($aPeriod[0]);
		$oTsdoc->setEnddate($aPeriod[1]);
		$tsdocform = $this->createForm('App\Form\TsdocType1',$oTsdoc);	
		return $tsdocform->createView();
	}
	protected function getPresenceForm(){
		$aPeriod = $this->getPeriodMonth(); 
		$oCdate = new Ccdate($aPeriod[0],$aPeriod[1]);
        	$Tspresence = $this->createForm('App\Form\CcdateType', $oCdate);
		return $Tspresence->createView();
	}
	protected function getPeriodMonth(){
		$aWeek  = $this->getStartAndEnd(date("W"),date("Y"),6);
 		$monthnumber = $aWeek[0]->format("m");
		$year = $aWeek[0]->format("Y");
		$startmonth = $this->getStartMonth($monthnumber,$year);
		$endmonth = $this->getEndMonth($monthnumber,$year);
		return array($startmonth,$endmonth);
	}
	//2020-01-04
	/*
	* Student events
	*/
	//Get event day for student
	protected function getStudentEvents($oStudent,$aDates,$oContract=null){
		$aAM=$this->getStudentDayEvent($oStudent,$aDates,1,$oContract);
		$aPM=$this->getStudentDayEvent($oStudent,$aDates,2,$oContract);
		return array('am'=>$aAM,'pm'=>$aPM);
	}
	protected function getStudentDayEvent($oStudent,$aDates,$option,$oContract=null){
		$aResult = array();
		foreach($aDates as $key=>$oDate){
			$aSubst = array(); //For verifying the repetition on the substitution's return
			$aSchedule = $this->getStudentDaySchedule($oStudent,$oDate,$option,$oContract);	
			$aSubstitution = $this->getSubsByStudent($oStudent,$oDate,$option,$oContract);
			if(count($aSchedule)==0 and count($aSubstitution)>0){
				foreach($aSubstitution as $index=>$oSubstitution){
					$aEvent = $this->getSubstByOptionBis($oSubstitution,null,$oDate,$option);
					if($aEvent!=null) $aResult[$index][$key] = $aEvent;
				}
			}else{
				//get the schedule of the teacher in taking into account the substitution there exists
				foreach($aSchedule as $index=>$oSchedule){
					$aEvent = $this->getDayScheduleEventTwo($oSchedule,$oDate,$option,$aSubst);
 					$idsub = isset($aEvent['idsubs'])?$aEvent['idsubs']:null; 
					$aResult[$index][$key] = $aEvent;
					if($idsub!=null and !isset($aSubst[$idsub])) $aSubst[$idsub]=$idsub;
				}
				//Take into account all the substitution which won't be taken before
				foreach($aSubstitution as $oSubstitution){
					$index = $index + 1;
					$idsub = $oSubstitution->getId();
					if(!isset($aSubst[$idsub])){
						$aEvent = $this->getSubstByOptionBis($oSubstitution,null,$oDate,$option);
						if($aEvent!=null) $aResult[$index][$key] = $aEvent;
						$aSubst[$idsub]=$idsub;
					}
				}
			}
		}
		return $aResult;
	}
	//Get day schedule event for the student
	protected function getDayScheduleEventTwo($oSchedule,$oDate,$option,$aSubs=null){
		//this employee is holder of a contract and is substituted by another teacher
		$oSubsHolder = $this->getSubstBySchedule($oSchedule,$oDate,$option);
		$oEmployee = $oSchedule->getEmployee();
		//This employee substituted some one on another contract
		$oSubstitution = $this->getSubsByScheduleTwo($oSchedule,$oDate,$option);
		//if there are no schedule nor substitution then return null
		if($oSchedule==null and $oSubstitution==null) return $oSchedule;
		//if option equal to 1 get hour of am else get hour of pm
		$hour = $option == self::OPTION_DAY_ONE?$this->getAmHour($oSchedule):$this->getPmHour($oSchedule);	
		if($oSubstitution!=null and $this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option);
		}elseif($oSubstitution and $this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and !isset($aSubs[$oSubstitution->getId()])){
			return $this->getSubstByOption($oSubstitution,$oSchedule,$oDate,$option);
		}
		//return $this->getScheduledForStudent($hour,$oSchedule,$oDate,$option);
		return $this->getScheduleByOption($hour,$oSchedule,$oDate,$option);
	}
	//Get substitution by schedule for the teacher event
	private function getSubsByScheduleTwo($oSchedule,$oDate,$option){
		$aSubstitution = $this->getSubsForScheduleTwo($oSchedule,$oDate,$option);
		foreach($aSubstitution as $oSubstitution){
			if($this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE){
				return $oSubstitution;
			}elseif($this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO){
				 return $oSubstitution;	
			}
		}
		return null;
	}
	public function getSubsForScheduleTwo($oSchedule,$oDate,$option){
		$aResult = array();
		$oContract = $oSchedule->getContracts();
		$oGroup = $oSchedule->getGroup();		
		$oRepSubs = $this->getRepository('BoAdminBundle:Substitution');
		if($oGroup!=null) $aSubstitution = $oRepSubs->getByGroup($oGroup,$oDate);
		elseif($oContract!=null) $aSubstitution = $oRepSubs->getByContract($oContract,$oDate);
		else return $aResult;
		foreach($aSubstitution as $oSubstitution){
			//Get the schedule of the substitution holder
			$aAgenda = $this->getHolderAgenda($oSubstitution,$oDate);
			if($aAgenda==null) continue;
			foreach($aAgenda as $oAgenda){

				//Check if the hour 
				if($this->getAmHour($oAgenda)>0 and $this->getAmHour($oSubstitution)>0 and $option == self::OPTION_DAY_ONE and $this->existDaySchedule($oAgenda,$oDate)==1){
					if(($oSubstitution->getStartam()>=$oSchedule->getStartam() and $oSubstitution->getStartam()<$oSchedule->getEndam()) or ($oSchedule->getStartam()>=$oSubstitution->getStartam() and $oSchedule->getStartam()<$oSubstitution->getEndam())) 	$aResult[]=$oSubstitution;
				}
				if($this->getPmHour($oAgenda)>0 and $this->getPmHour($oSubstitution)>0 and $option == self::OPTION_DAY_TWO and $this->existDaySchedule($oAgenda,$oDate)==1){
					if(($oSubstitution->getStartpm()>=$oSchedule->getStartpm() and $oSubstitution->getStartpm()<$oSchedule->getEndpm()) or ($oSchedule->getStartpm()>=$oSubstitution->getStartpm() and $oSchedule->getStartpm()<$oSubstitution->getEndpm())) 	$aResult[]=$oSubstitution;
				}
			}
		}
		return $aResult;
	}
	public function updateInfoStudent($aStudents){
		$aResult = array();
		foreach($aStudents as $oStudent){
			$aResult[] = $this->updateEnddate($oStudent);
		}
		return $aResult;	
	}
	public function updateEnddate($oStudent){
		$oEnddate = $oStudent->getEnddate();
		$aContracts = $oStudent->getContracts();
		foreach($aContracts as $oContract){
			if($oContract->getEnddate()>$oStudent->getEnddate()){
				$oStudent->setEnddate($oContract->getEnddate());
			}			
		}
		if($oEnddate!=$oStudent->getEnddate()) $oStudent = $this->updateEntityTwo($oStudent);
		return $oStudent;	
	}
	public function checkAndCreate($absence,$oStudent){
		$oStartdate = $absence->getStartdate();
		$oEnddate = $absence->getEnddate();
		while($oStartdate<$oEnddate){
			$bAmAbs = $this->areAllMembersAbsent($oStartdate,$oStudent,1);
			$bPmAbs = $this->areAllMembersAbsent($oStartdate,$oStudent,2);
			if($bPmAbs==true and $bAmAbs==true) $res = $this->createGroupAbs($oStartdate,$absence,$oStudent,3);
			elseif($bAmAbs==true) $res = $this->createGroupAbs($oStartdate,$absence,$oStudent,1);
			elseif($bPmAbs==true) $res = $this->createGroupAbs($oStartdate,$absence,$oStudent,2);
			$oStartdate = $this->getDatePlus($oStartdate,1);
		}
		return true;
	}
	//check if all members of a group are absent and return true hhen it's true and false otherwise
	public function areAllMembersAbsent($oDate,$oStudent,$option){
		$aSchedule = $oStudent->getAgenda();
		if($oStudent->getGroup() and $oGroup = $oStudent->getGroup()){
			$aStudents = $oGroup->getStudents();
			foreach($aStudents as $oStudent){
				$bIsAbsent = $this->isAbsentStudent($oStudent,$oDate,$option);
				if($bIsAbsent==false) return false;
			}
			return true;
		}
		return false;
	}
	//creating the absence group
	public function createGroupAbs($oDate,$absence,$oStudent,$option){
		$oAbsenceGroup = $this->initAbsGroup($oAbsence,$oStudent,$option);
		return $this->updateEntityTwo($oAbsenceGroup);
	}
	public function initAbsGroup($oAbsence,$oStudent,$option){
		$aAmorpm = array('1'=>'AM','2'=>'PM','3'=>'ALL');
		$oAbsGroup = new AbsenceGroup();
		if($oStudent->getGroup() and $oGroup = $oStudent->getGroup()) $oAbsGroup->setGroup($oGroup);
		else return null;
		$oAbsGroup->setStartdate($oDate);
		$oAbsGroup->setEnddate($oDate);
		$oAbsGroup->setAmorpm($aAmorpm[$option]);
		$oAbsGroup->setMotif("Other/Autre");
		$oAbsGroup->setNumberday(1);
		$oAbsGroup->setDayns(1);
		$oAbsGroup->setDayabs(0);
		$oAbsGroup->setNoshow(true);
		if($option == self::OPTION_DAY_ONE or $option == self::OPTION_DAY_THREE) $oAbsGroup->setNsam(true);
		if($option == self::OPTION_DAY_TWO or $option == self::OPTION_DAY_THREE) $oAbsGroup->setNspm(true);
		$oAbsGroup->setAgenda($oAbsence->getAgenda());
		$oAbsGroup->setTeacherpresence($oAbsence->getTeacherpresence());
		$oAbsGroup->setTpam($oAbsence->getTpam());
		$oAbsGroup->setTppm($oAbsence->getTppm());
		$oAbsGroup->setCreateby($oAbsence->Createby());
		$oAbsGroup->setCreationdate($oAbsence->Creationdate());
		return $oAbsGroup;
	}
	//return null when there is not a usedhour line in the database for the contract and the date given as parameter, otherwise return a array containing AM an PM hours
	public function getUsedHoursBy($oDate,$oContract,$oGroup=null){
		if($oGroup!=null) $oUsedhour = $this->getUhObject($oDate,null,$oGroup);
		if($oContract!=null) $oUsedhour = $this->getUhObject($oDate,$oContract);
		if($oUsedhour==null) return $oUsedhour;
		return array('am' => $oUsedhour->getHam(),'pm' => $oUsedhour->getHpm()); 
	}
	public function getUhObject($oDate,$oContract,$oGroup=null){
		$oUH = $this->getRepository('BoAdminBundle:Usedhour');
		if($oGroup!=null) $aUsedhour = $oUH->getUhForGroup($oDate,$oGroup);
		if($oContract!=null) $aUsedhour = $oUH->getUhForContract($oDate,$oContract);
		if(isset($aUsedhour) and count($aUsedhour)>0) return $aUsedhour[0];
		return null;
	}
	//Get usedhour in the database
	protected function getHourInDB($oDate,$oContract,$oGroup=null){
		$aUsedhour = $this->getUsedHoursBy($oDate,$oContract,$oGroup);
		if($aUsedhour==null or count($aUsedhour)==0) return 0;
		return array_sum($aUsedhour);
	}
	//Create Cron activity
	public function createCronActivity($user,$message){
		$oActivity = new Activities();
		$oActivity->setUser($user);
		$oActivity->setMessage($message);
		return $this->updateEntity($oActivity);
	}
	/*
	* Robot functions
	*/
	protected function treatContract($oDate,$opt=null){
		set_time_limit(70400);
		$sAmteacher=$sPmteacher=null;
		$oRepParam = $this->getRepository('BoAdminBundle:Param');
		//Initialize variables
		$aGoup = array();
		$aIdCont = array();
		$aIdgroup = array();
		$aStudent = array();
		//Get all the in progress contracts	
		$aSchedule = $this->getAgenda($oDate);	

		//$aSchedule = $this->getRepository('BoAdminBundle:Agenda')->findByGroup(480);
		
		foreach($aSchedule as $oSchedule){
			//if the schedule entity is not for this day 
			if($this->existDaySchedule($oSchedule,$oDate)==0) continue;
			$oContract = $oSchedule->getContracts();
			//If it is not group contract and student absent all day long
			if($oSchedule->getGroup()==null and $this->isAbsentStudent2($oSchedule,$oDate,3)==true) continue;
			//continue;
			//If the contract is not l2l contract continue
			if($oContract!=null and $clic = $oContract->getClic() and $clic==false) continue;			
			if($opt==null){
				//Check if a line of usedhour already exist for this contract
				$Duh = $this->getRepository('BoAdminBundle:Usedhour')->getUhForContract($oDate,$oContract);
				if(count($Duh)>0) continue;
			}
			if($oContract!=null){
				//Get array of students who are on the contract
				$aStudent = $oContract->getStudents();
				//If there is no student then continue to 
				if(count($aStudent)==0) continue;
				//Not to take into account all contracts which are not scheduled
				if($this->isScheduledContract($oContract,$oDate)==false) continue;
			}
			//Not to take into account a contract if it's holiday in the province where the course is taking place
			if($this->isHolidaysBy($oDate,$oSchedule,3)==true) continue;
			//Group contracts treatment
			if(count($aStudent)>1 or $oContract->getGroup() or $oSchedule->getGroup()){
				if($oContract->getGroup() and $oGroup=$oContract->getGroup() and !isset($aGoup[$oGroup->getId()])){
					//Avoiding all group already treated
					if(isset($aIdgroup[$oGroup->getId()])) continue;
					//Save the id of group treated
					$aIdgroup[$oGroup->getId()]=$oGroup->getId();
					$aSUH = $this->getGroupArrayHour($oSchedule,$aStudent[0],$oDate);
					$fUhdb = $this->getHourInDB($oDate,null,$oGroup);
					$aUsedhour = $this->getUsedHoursBy($oDate,null,$oGroup);
					$hour = floatval($aSUH['am'])+floatval($aSUH['pm']);
					if($hour>0){
						if($aUsedhour==null) $this->saveUsedHour($oGroup,$hour,2,$oDate,null,$aSUH,$oSchedule);
						elseif($fUhdb!=$hour) $this->updateUsedHour(null,$oDate,$aSUH,$oSchedule,$aUsedhour);
					}
				}elseif($oSchedule->getGroup() and $oGroup=$oSchedule->getGroup()){
					//Avoiding all group already treated
					if(isset($aIdgroup[$oGroup->getId()])) continue;
					//Save the id of group treated
					$aIdgroup[$oGroup->getId()]=$oGroup->getId();
					$aSUH = $this->getGroupArrayHour($oSchedule,$aStudent[0],$oDate);
					$aUsedhour = $this->getUsedHoursBy($oDate,null,$oGroup);
					$fUhdb = $this->getHourInDB($oDate,null,$oGroup);
					$hour = floatval($aSUH['am'])+floatval($aSUH['pm']);
					if($hour>0){
						if($aUsedhour==null) $this->saveUsedHour($oGroup,$hour,2,$oDate,null,$aSUH,$oSchedule);
						elseif($fUhdb!=$hour) $this->updateUsedHour(null,$oDate,$aSUH,$oSchedule,$aUsedhour);
					}
				}elseif(!isset($aIdCont[$oContract->getId()])){
					$aIdCont[$oContract->getId()]=$oContract->getId();
					$aSUH = $this->getStudentArrayHour($oSchedule,null,$oDate);
					$aUsedhour = $this->getUsedHoursBy($oDate,$oContract);
					$fUhdb = $this->getHourInDB($oDate,$oContract);
					$hour = floatval($aSUH['am'])+floatval($aSUH['pm']);
					if($hour>0){
						if($aUsedhour==null) $this->saveUsedHour($oContract,$hour,1,$oDate,null,$aSUH,$oSchedule);
						elseif($fUhdb!=$hour) $this->updateUsedHour($oContract,$oDate,$aSUH,$oSchedule,$aUsedhour);
					}
				}
			}elseif(count($aStudent)==1){
				$oStudent = $aStudent[0];
				$aSUH = $this->getStudentArrayHour($oContract,$oStudent,$oDate);
				$aUsedhour = $this->getUsedHoursBy($oDate,$oContract);
				$hour = floatval($aSUH['am'])+floatval($aSUH['pm']);
				if($hour>0){
					if($aUsedhour==null) $this->saveUsedHour($oContract,$hour,1,$oDate,$oStudent,$aSUH,$oSchedule);
					elseif(array_sum($aUsedhour)!=$hour) $this->updateUsedHour($oContract,$oDate,$aSUH,$oSchedule,$aUsedhour);
				}
			}
		}
		return true;
	}
	protected function treatOneContract($oDate,$oEnd,$aRes){
		$oSchedule = null;
		$idContract = isset($aRes['idcontract'])?$aRes['idcontract']:null;
		$idAgenda = isset($aRes['idagenda'])?$aRes['idagenda']:null;
		if($idContract!=null){
			$oContract = $this->getRepository('BoAdminBundle:Contracts')->find($idContract);
			$aSchedule = $this->getScheduleByContract($oContract,$oDate);
			$aSchedule = $this->getRealScheduled($aSchedule,$oDate);
			if(isset($aSchedule[0])) $oSchedule = $aSchedule[0]; 
			else return false;
		}
		if($idAgenda!=null){
			$oSchedule = $this->getRepository('BoAdminBundle:Agenda')->find($idAgenda);
			$oContract = $oSchedule->getContracts();
		}
		if($oSchedule==null) return false;
		$sAmteacher=$sPmteacher=null;
		$oRepParam = $this->getRepository('BoAdminBundle:Param');

		//if the schedule entity is not for this day 
		if($this->existDaySchedule($oSchedule,$oDate)==0) return false;
		if($oSchedule->getGroup()==null and $this->isAbsentStudent2($oSchedule,$oDate,3)==true) return false;
		if($oContract==null) return false;
		$clic = $oContract->getClic();
		if($clic==false) return false;
		//Get array of students who are on the contract
		$aStudent = $oContract->getStudents();
		//If there is no student then continue to 
		if(count($aStudent)==0) return false;
		//Do not to take into account all contract which not schedule
		if($this->isScheduledContract($oContract,$oDate)==false) return false;
		//Do not to take into account this contract if it's holiday for the province where the course is taking place
		if($this->isHolidaysBy($oDate,$oSchedule,3)==true) return false;
		//Group contracts treatment
		if(count($aStudent)>1 or $oContract->getGroup()){
			if($oContract->getGroup() and $oGroup=$oContract->getGroup() and !isset($aGoup[$oGroup->getId()])){
				//Group treatment
				//Check if a line of usedhour already exist for this group
				$Duh = $this->getRepository('BoAdminBundle:Usedhour')->getUhForGroup($oDate,$oGroup);
				if(count($Duh)>0) return false;
				//Save the id of group treated
				$aSUH = $this->getGroupArrayHour($oContract,$aStudent[0],$oDate);
				$hour = floatval($aSUH['am'])+floatval($aSUH['pm']);
				if($hour>0){
					$this->saveUsedHour($oContract,$hour,1,$oDate,$oStudent,$aSUH,$oSchedule);
					$iGroup = $iGroup+1; 
					$iContract = $iContract+1; 
				}
			}elseif(!isset($aIdCont[$oContract->getId()])){
				$aIdCont[$oContract->getId()]=$oContract->getId();
				$oStudent = null;
				$aSUH = $this->getStudentArrayHour($oContract,null,$oDate);
				$hour = floatval($aSUH['am'])+floatval($aSUH['pm']);
				if($hour>0){
					$this->saveUsedHour($oContract,$hour,1,$oDate,$oStudent,$aSUH,$oSchedule);
					$iContract = $iContract+1; 
				}
			}
		}elseif(count($aStudent)==1){
			$oStudent = $aStudent[0];
			$aSUH = $this->getStudentArrayHour($oContract,$oStudent,$oDate);
			$aUsedhour = $this->getUsedHoursBy($oDate,$oContract);
			$hour = floatval($aSUH['am'])+floatval($aSUH['pm']);
			if($hour>0){
				if($aUsedhour==null) $this->saveUsedHour($oContract,$hour,1,$oDate,$oStudent,$aSUH,$oSchedule);
				elseif(array_sum($aUsedhour)!=$hour) $this->updateUsedHour($oContract,$oDate,$aSUH,$oSchedule,$aUsedhour);
			}
		}
		return true;
	}
	//Save used hour for contract or Group
	protected function saveUsedHour($oEntity,$hour,$type,$oDate,$oStudent=null,$aSUH=null,$oSchedule=null){
		$fUhdb = 0;
		$iRH = $aRDD = null;
		$bNotif=false;
		//get contract hour threshold value
		$threshold = $this->getRepository('BoAdminBundle:Param')->getParam("contract_hours_threshiold",35);
		//Save updatedate for contract or for group
		$oEntity->setDateusedhour($oDate);
		//If is set usedhours in the entity
		$iOldUH = $oEntity->getUsedhours()?$oEntity->getUsedhours():0;
		$iUsedHour = floatval($iOldUH)+floatval($hour); 
		//Save usedhour for contract or for group
		$oEntity->setUsedhours($iUsedHour);
		if($oEntity->getTotalhours()) $iRH = floatval($oEntity->getTotalhours())-floatval($oEntity->getUsedhours());
		//Compare this parameter with the current 
		if($iRH<$threshold){
			$bNotif=true;
		}elseif($iRH>0 and $oSchedule!=null){
			$oEntity->setRhour($iRH);
			$aRDD = $this->getEstimatedDate($oSchedule,$iRH,$oDate);
			if(isset($aRDD[0])){ 
				if($aRDD[0]!=null) $oEntity->setRdaynumber($aRDD[0]);
				if($aRDD[1]!=null and $aRDD[1])  $oEntity->setRenddate($aRDD[1]);
			}
		}
		$this->updateEntity($oEntity);
		$this->createUsedhour($oEntity,$hour,$type,$oDate,$oStudent,$bNotif,$iRH,$iUsedHour,$aSUH,$aRDD);		
		return $oEntity;
	}
	//Save used hour for contract or Group
	protected function updateUsedHour($oContract,$oDate,$aSUH,$oSchedule,$aUsedhour){
		if($oContract==null) $oGroup = $oSchedule->getGroup();
		else $oGroup = null;
		$fNew = floatval($aSUH['am'])+floatval($aSUH['pm']);
		$fOld = array_sum($aUsedhour);
		$fDiff = $fNew - $fOld;
		if($oContract!=null) $oUsedhour = $this->getUhObject($oDate,$oContract);
		elseif($oGroup!=null) $oUsedhour = $this->getUhObject($oDate,null, $oGroup);
		$oUsedhour->setHam(floatval($aSUH['am']));
		$oUsedhour->setHpm(floatval($aSUH['pm']));
		$oUsedhour->setHour($fNew);
		$iRH = $aRDD = null;
		$bNotif=false;
		//get contract hour threshold value
		$threshold = $this->getRepository('BoAdminBundle:Param')->getParam("contract_hours_threshiold",35);
		//Save updatedate for contract or for group
		if($oContract!=null) $oContract->setDateusedhour($oDate);
		if($oGroup!=null) $oGroup->setDateusedhour($oDate);
		//If is set usedhours in the entity
		if($oContract!=null) $iOldUH = $oContract->getUsedhours()?$oContract->getUsedhours():0;
		elseif($oGroup!=null) $iOldUH = $oGroup->getUsedhours()?$oGroup->getUsedhours():0;
		$iUsedHour = floatval($iOldUH)+$fDiff; 
		//Save usedhour for contract or for group
		$oContract->setUsedhours($iUsedHour);
		if($oContract!=null) $iRH = floatval($oContract->getTotalhours())-floatval($oContract->getUsedhours());
		if($oGroup!=null)  $iRH = floatval($oGroup->getTotalhours())-floatval($oGroup->getUsedhours());
		//Compare this parameter with the current 
		if($iRH<$threshold){
			$bNotif=true;
			$oUsedhour->setNotif($bNotif);
		}elseif($iRH>0 and $oSchedule!=null){
			if($oContract!=null) $oContract->setRhour($iRH);
			$aRDD = $this->getEstimatedDate($oSchedule,$iRH,$oDate);
			if(isset($aRDD[0])){ 
				if($aRDD[0]!=null) $oContract->setRdaynumber($aRDD[0]);
				if($aRDD[1]!=null and $aRDD[1])  $oContract->setRenddate($aRDD[1]);
			}
		}
		if($oContract!=null) $this->updateEntity($oContract);
		if($oGroup!=null) $this->updateEntity($oGroup);
		$this->updateEntity($oUsedhour);
		return true;
	}
	protected function getAgenda($oDate=null){
		if($oDate==null) $oDate = $this->getToday();
		return $this->getRepository('BoAdminBundle:Agenda')->getByDate($oDate);
	}
	protected function createUsedhour($oEntity,$hour,$type,$oDate,$oStudent,$bNotif,$iRH,$iDtuh=null,$aSUH=null,$aRDD=null){
		if($iRH<0) $iRH=0;
		$oUsedhour = new Usedhour();
		$oUsedhour->setHour($hour);
		$oUsedhour->setDdate($oDate);
		$oUsedhour->setNotif($bNotif);
		$oUsedhour->setRest($iRH);
		if($iDtuh!=null) $oUsedhour->setDtuh($iDtuh);
		if($aSUH!=null and isset($aSUH['am'])) $oUsedhour->setHam($aSUH['am']);
		if($aSUH!=null and isset($aSUH['pm'])) $oUsedhour->setHpm($aSUH['pm']);
		if($type==1){ 
			if($oStudent) $oUsedhour->setStudent($oStudent);
			if($oEntity) $oUsedhour->setContract($oEntity);
		}elseif($oEntity){
		     	$oUsedhour->setGroup($oEntity);
		}
		if($aRDD!=null){
			$oUsedhour->setRdn($aRDD[0]);
			$oUsedhour->setEstimateddate($aRDD[1]);
		}
		$res = $this->updateEntity($oUsedhour);
		if($res>0 and $oUsedhour){
			$this->createMonthlyBy($oUsedhour);
			if($aSUH!=null and (isset($aSUH['tam']) or isset($aSUH['tpm']))) $oUsedhour = $this->addTeachersToUH($aSUH['tam'],$aSUH['tpm'],$oUsedhour);
		}
		return true;	
	}
	protected function addTeachersToUH($sAmteacher,$sPmteacher,$oUsedhour){
		$aIdAm = explode(",",$sAmteacher);
		$aIdPm = explode(",",$sPmteacher);
		if(isset($aIdAm[0])) $oUsedhour = $this->addTeacherTo($aIdAm,$oUsedhour,1);
		if(isset($aIdPm[0])) $oUsedhour = $this->addTeacherTo($aIdPm,$oUsedhour,2);
		return $oUsedhour;
	}
	/*
	* add teachers to usedhour table
	* @param: $aIds array of teacher id, $option integer (1 for AM and 2 for PM)
	* @param: \App\Entity\Usedhour $usedhour
	* @return \App\Entity\Usedhour $usedhour 
	*/
	protected function addTeacherTo($aIds,$oUsedhour,$option){
		$oRepTeacher = $this->getRepository('BoAdminBundle:Employee');
		foreach($aIds as $id){
			$oEmployee = $oRepTeacher->find($id);
			if($this->isAddedTeacher($oUsedhour,$oEmployee)==true) continue;
			if($oEmployee){
				if($option == self::OPTION_DAY_ONE) $oUsedhour->addAmteacher($oEmployee);
				if($option == self::OPTION_DAY_TWO) $oUsedhour->addPmteacher($oEmployee);
			} 
		}
		return $oUsedhour;
	}
	protected function isAddedTeacher($oUserhour,$oEmployee){
		if(is_array($oUserhour->getAmteacher())){
			foreach($oUserhour->getAmteacher() as $oTeacher){
				if($oTeacher!=null and $oEmployee!=null and $oTeacher->getId()==$oEmployee->getId()) return true;
			}
		}
		if(is_array($oUserhour->getPmteacher())){
			foreach($oUserhour->getPmteacher() as $oTeacher){
				if($oTeacher!=null and $oEmployee!=null and $oTeacher->getId()==$oEmployee->getId()) return true;
			}
		}
		return false;
	}
	protected function getPeriodDate(){
		$oRepParam = $this->getRepository('BoAdminBundle:Param');
		return $oRepParam->getParam("cron_robot_period",37);
	}
	private function getContratcsByDate($oDate=null){
		if($oDate==null) $oDate = $this->getToday();
		return $this->getRepository('BoAdminBundle:Contracts')->getByDate($oDate);
	}
	private function getGroupsByDate($oDate=null){
		if($oDate==null) $oDate = $this->getToday();
		return $this->getRepository('BoAdminBundle:Group')->getByDate($oDate);
	}
	private function updateContract($fNewhour,$fOldhour,$oContract){
		$oContract->setUsedhours($oContract->getUsedhours()-$fOldhour+$fNewhour);
		return $this->updateEntity($oContract);
	}	
	private function updateRhour($oContracts){
		$oContracts->setRhour(floatval($oContracts->getTotalhours())-floatval($oContracts->getUsedhours()));
		$this->updateEntity($oContracts);
	} 
	public function updategroup(){
		$aActiveGroup = $this->getRepository('BoAdminBundle:Group')->findBy(array('status'=>1));
		foreach($aActiveGroup as $oGroup){
			$this->setStatusFor($oGroup);
		}
		$aActiveGroup = $this->getRepository('BoAdminBundle:Group')->findBy(array('status'=>2));
		foreach($aActiveGroup as $oGroup){
			$this->setStatusFor($oGroup);
		}
		return;
	}
	protected function recordRobotWork($oDate,$start,$end,$sNote=null){
		$oRobot = $this->getTodayWork($oDate);
		if($oRobot){
			$aNumCont = $this->getNumberContract($oRobot);
			$iContract = $aNumCont[0]; 
			$iGroup  = $aNumCont[1];
			return $this->updateRobotWork($oRobot,$start,$end,$iGroup,$iContract,$sNote);
		}
		$aNumCont = $this->getNumberContract(null,$oDate);
		$iContract = $aNumCont[0]; 
		$iGroup  = $aNumCont[1];
		$oRobot = new Robot($oDate);
		$oRobot->setStart($start->format("d-m-Y H:i:s"));
		$oRobot->setEnd($end->format("d-m-Y H:i:s"));
		$oRobot->setNbcontract($iContract);
		$oRobot->setNbgroup($iGroup);
		if($sNote!=null) $oRobot->setNote($sNote);
		$res = $this->updateEntity($oRobot);
		if($res>0 and $oRobot) return $oRobot;
	}
	private function getNumberContract($oRobot,$oDate=null){
		$oRepUh = $this->getRepository('BoAdminBundle:Usedhour');
		if($oDate==null) $oDate = $oRobot->getDdate();
		$idcontracts = $oRepUh->getNumberByDate($oDate,1);
		$idgroups = $oRepUh->getNumberByDate($oDate,2);
		return array($idcontracts,$idgroups);
	}
	private function getTodayWork($oDate=null){
		if($oDate==null) $oDate=$this->getToday();		
		$aRobot = $this->getRepository("BoAdminBundle:Robot")->getRobotWork($oDate);
		if(count($aRobot)==1){
			return $aRobot[0];
		}
		return null;
	}
	private function updateRobotWork($oRobot,$start,$end,$iGroup,$iContract,$sNote){
		$oRobot->setStart($start->format("d-m-Y H:i:s"));
		$oRobot->setEnd($end->format("d-m-Y H:i:s"));
		$oRobot->setNbcontract($iContract);
		$oRobot->setNbgroup($iGroup);
		if($sNote!=null) $oRobot->setNote($sNote);
		return $this->updateEntity($oRobot);
	}
        	/*
	* Robot functions
	*/
	protected function calculateTeacherHours($oDate){
		set_time_limit(70400);
		$sAmteacher=$sPmteacher=null;
		$oRepParam = $this->getRepository('BoAdminBundle:Param');
		//Initialize variables
                $aEmployee = array();
		//Get all the in progress contracts	
		$aSchedule = $this->getAgenda($oDate);			
		foreach($aSchedule as $oSchedule){
                        $oEmployee = $oSchedule->getEmployee();
                        if($oEmployee==null or isset($aEmployee[$oEmployee->getId()])) continue;
                        $aEmployee[$oEmployee->getId()] = $oEmployee->getId(); 
                        //$oTH = $this->createTeacherHour($oEmployee,$oDate);
		}
		return true;
	}
        public function createTeacherHour($oEmployee,$oDate){
            //matin option egale 1
            $am = $this->getHourByOption($oEmployee,$oDate,1);   
            $amstudents = $this->getStudentByOption($oEmployee,$oDate,1);
            if($this->isAbsentEmployee($oEmployee,$oDate,1)==true) $lam = "ABS";//Get teacher legend for the morning
            elseif(floatval($am) > 0)  $lam = "P";
            else $lam = $this->getLegendByOption($oEmployee,$oDate,1);
            //après-midi avant 17 heures option egale 2
            $pm = $this->getHourByOption($oEmployee,$oDate,2);
            $pmstudents = $this->getStudentByOption($oEmployee,$oDate,2);
            //après-midi après 17 heures option egale 3
            $ev = $this->getHourByOption($oEmployee,$oDate,3);
            $evstudents = $this->getStudentByOption($oEmployee,$oDate,3);
            if($this->isAbsentEmployee($oEmployee,$oDate,2)==true){ 
                $lam = "ABS";
                $lev = "ABS";
            }elseif(floatval($pm) > 0 or floatval($ev) > 0){
                $lpm = floatval($pm) > 0 ?"P":$this->getLegendByOption($oEmployee,$oDate,2);
                $lev = floatval($ev) > 0 ? "P":$this->getLegendByOption($oEmployee,$oDate,3);
            }else{
                echo "ici<br>";
                $lpm = $this->getLegendByOption($oEmployee,$oDate,2);
                $lev = $lpm;
            }
                        echo $oEmployee->getId()." ".$this->getFullnameOf($oEmployee)."<br>";
                        echo "AM:".$am."<br>";
                        echo "LAM:".$lam."<br>";
                        echo "SAM:".$amstudents."<br>";
                        echo "PM:".$pm."<br>";
                        echo "LPM:".$lpm."<br>";
                        echo "SPM:".$pmstudents."<br>";
                        echo "EV:".$ev."<br>";
                        echo "LEV:".$lev."<br>";  
                        echo "SEV:".$evstudents."<br><br>";
        }
}







