<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Form\EmployeeType3;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DefaultController extends CommonController
{
    public function indexAction(Request $request)
    {
		$employee = new Employee();
		$employee->setSexe("Masculin");
		$employee->setName("Dortoir");
		$employee->setFirstname("Maxime");
		$employee->setEmail("maxime.dortoir@gmail.com");
		$employee->setMobileNumber("819 230 0667");
		$employee->setBirthdayDate("1974-12-04");
		$employee->setHiringDate("2020-12-04");

		$encoders   = [new XmlEncoder(), new JsonEncoder()];
		$normalizer = [new ObjectNormalizer()];
		$serializer = new Serializer($normalizer, $encoders);

		$jsonContent = $serializer->serialize($employee, 'json');

		echo $jsonContent;
		die;


		//get question response starter
		$qr_starter = $this->getRepository('BoAdminBundle:Param')->getParam("secure_starter_param",42);
		if($qr_starter==1){
			$oRepResp = $this->getRepository('BoSecureBundle:Response');
			//get all response of the user and check in if the total is equal to 3
			$aResponse = $oRepResp->getByUser($oUser);
			$iNumber = count($aResponse);
			if($iNumber<3){
				return $this->redirectToRoute('bo_secure_homepage',array('lang'=>$this->getLangBy($iNumber,$aResponse)));
			}
		}
		$this->setUserLocale($request->getLocale());
		$user=$this->initializeSession($request->getLocale());
		$this->verifySession();
		//Show the notification if there exist the contract near to end
        	$oRepUh = $this->getRepository('BoAdminBundle:Usedhour');
		$lastRobotDay = $this->getRobotLastDay()!=null?$this->getRobotLastDay():$this->getYesterday();
		$aUsedHour = $oRepUh->getUsedhour($lastRobotDay);
		$cne = $this->get("session")->get('cne');
		if(count($aUsedHour)>0 and $cne==null){
			return $this->redirectToRoute('alert_index');
		}
		//Initialize the variables
		$aContract = $aCurrentWeek = $aNextWeek = $aPrevWeek = $aEvents = $aNextEvents = null;
		$this->setActivity("Connexion à SLS-MANAGER");
		$em = $this->getDoctrine()->getManager();
		$aTickets=$aStudentAbs = $aTeacherAbs = null;
		//get info maintenance
		$iParamval = $em->getRepository('BoAdminBundle:Param')->getParam("maintenance_inprogress",8);
		//If maintenance display maintenance page
		if($iParamval==1) return $this->redirectToRoute('bo_admin_maintenance');			
		//Vérify if it's the first connexion of this user
		//existUserSession return the value 0 if it's the first connexion or the value 1 else
		//if($this->existUserSession()==0) return $this->redirect($this->generateUrl('fos_user_change_password2'));
		$aSecurityCotes=null;
		$ContractEntity = $em->getRepository('BoAdminBundle:Contracts');
		$employee=$user?$user->getEmployee():null;
		$aTeacherAbs = $this->getTeacherAbsForAdvisor($employee);
		$aStudentAbs = $this->getStudentAbsForAdvisor($employee);
		$this->setSessionByName("user_profile",$this->getUserProfile()); 
		//$aNotification = $this->getNotification($employee);
		if($employee){
			$aContract = $this->getTeacherContracts($employee);
			$aInvitation = $this->getRepository('BoAdminBundle:Invitation')->getByEmployee($employee);
			$this->setSessionByName('profile',$employee->getProfil());
			$aSecurityCotes = $em->getRepository('BoAdminBundle:SecurityCote')->findBy(array('employee'=>$employee));
			//Get all ticket except the ones closed
			$aTickets = $em->getRepository('BoAdminBundle:Tickets')->getEmployeeTicketsBy($employee->getId());
			if(isset($aTickets[0])) $aStudents = $aTickets[0]->getStudents();
		}
		//Get training events for this admin member if a contract scheduled for him
		$aCurrentWeek = $this->getWeekSheetDays($this->getToday());
		$aPrevWeek = $this->getPreviousWeek($aCurrentWeek);
		$aNextWeek = $this->getNextWeek($aCurrentWeek);
		$aEvents = $this->generateEvents($employee,$aCurrentWeek);
		$aNextEvents = $this->generateEvents($employee,$aNextWeek);
		$aPreventEvents = $this->generateEvents($employee,$aPrevWeek);

        	return $this->render('BoAdminBundle:Default:index.html.twig',
			array(
			'employee'=>$employee,
			'tickets'=>$aTickets,
			'contracts'=>$aContract,
			'status'=> $this->getStatusTicket(),
			'today'=>$this->getToday(),
			'securityCotes'=>$aSecurityCotes,
			'invitations'=>$aInvitation,
			'b_invite'=>$this->getExistInvite($aInvitation),
			'welcome'=>$this->getWelcomeMessage('Admin'),
			'dwelcome'=>$this->getParam("teacher_display_message",28),
			'campuss'=>$em->getRepository('BoAdminBundle:Campus')->findAll(),
			'campuss'=>$em->getRepository('BoAdminBundle:Campus')->findAll(),
			'dispbirth'=>$em->getRepository('BoAdminBundle:Param')->getParam("employee_display_birdthday",34),
			'imgname'=>$this->getRandomImage(),
			'form' => $this->getTicketFormView(),
			'absence_form' => $this->getAbsenceFormView(),
			'tsdoc_form' => $this->getTsDocForm(),
			'tsp_form'=>$this->getPresenceForm(),
			'dnb'=>1,//display the action pane for the admin's interface
			'title' => "Home",
			'profile'=> $this->getUserProfile(),
			'tb_am'=>$aEvents['am'],
			'tb_pm'=>$aEvents['pm'],
			'dates'=>$aCurrentWeek,
			'pdates'=>$aPrevWeek,
			'ndates'=>$aNextWeek,
			'pm'=>"tabeau-bord",
			'sm'=>"home",
		));
    }
    /**
    * load schedule by date and id employee using ajax 
    */
    public function loadscheduleAction(Request $request)
    {
		$oEmpRep = $this->getRepository('BoAdminBundle:Employee');	
		if($request->isXmlHttpRequest())
		{	
			$data = $request->request->get('data');	
			$aData = explode("#",$data);
			$idemployee = $aData[0];
			$date =  new \DateTime($aData[1]);
		}
		//End to delete
		$employee = $oEmpRep->find($idemployee);
		$aCurrentWeek = $this->getWeekSheetDays($date);
		$aPrevWeek = $this->getPreviousWeek($aCurrentWeek);
		$aNextWeek = $this->getNextWeek($aCurrentWeek);
		$aEvents = $this->generateEvents($employee,$aCurrentWeek);
		$aNextEvents = $this->generateEvents($employee,$aNextWeek);
		
        	return $this->render('BoHomeBundle:Default:event-tab.html.twig',
			array(
				'employee'=>$employee,
				'tb_am'=>$aEvents['am'],
				'today'=>$this->getToday(),
				'tb_pm'=>$aEvents['pm'],
				'dates'=>$aCurrentWeek,
				'pdates'=>$aPrevWeek,
				'ndates'=>$aNextWeek,
				'pm'=>"personnel",
				'sm'=>"employee",
			)
		);
    }
    public function overrideAction(Request $request)
    { 
		$this->setActivity("Connexion à SLS-MANAGER:override");
		$em = $this->getDoctrine()->getManager();
		$this->setUserLocale($request->getLocale());
		$aSecurityCotes=$aInvitation=$aContract=null;
		$ContractEntity = $em->getRepository('BoAdminBundle:Contracts');
		$user=$this->initializeSession($request->getLocale());
		$employee=$user?$user->getEmployee():null;
		$this->setSessionByName("user_profile",$this->getUserProfile()); 
		//$aNotification = $this->getNotification($employee);
		if($employee){
			$this->setSessionByName('profile',$employee->getProfil());
			$aContract = $ContractEntity->getTeacherContracts($employee);
			$aInvitation = $this->getRepository('BoAdminBundle:Invitation')->getByEmployee($employee);
			$aSecurityCotes = $em->getRepository('BoAdminBundle:SecurityCote')->findBy(array('employee'=>$employee));
		}	
		$aTickets = $em->getRepository('BoAdminBundle:Tickets')->getEmployeeTickets($employee->getId(),1);
		if(isset($aTickets[0])) $aStudents = $aTickets[0]->getStudents();
			$aCurrentWeek = $this->getWeekSheetDays($this->getToday());
			$aPrevWeek = $this->getPreviousWeek($aCurrentWeek);
			$aNextWeek = $this->getNextWeek($aCurrentWeek);
			$aEvents = $this->generateEvents($employee,$aCurrentWeek);
			$aNextEvents = $this->generateEvents($employee,$aNextWeek);
			$aPreventEvents = $this->generateEvents($employee,$aPrevWeek);

        	return $this->render('BoAdminBundle:Default:index.html.twig',
			array(
			'employee'=>$employee,
			'tickets'=>$aTickets,
			'contracts'=>$aContract,
			'status'=> $this->getStatusTicket(),
			'invitations'=>$aInvitation,
			'securityCotes'=>$aSecurityCotes,
			'welcome'=>$this->getWelcomeMessage('Admin'),
			'campuss'=>$em->getRepository('BoAdminBundle:Campus')->findAll(),
			'imgname'=>$this->getRandomImage(),
			'title' => "Home",
			'tb_am'=>$aEvents['am'],
			'tb_pm'=>$aEvents['pm'],
			'dates'=>$aCurrentWeek,
			'pdates'=>$aPrevWeek,
			'ndates'=>$aNextWeek,
			'pm'=>"tabeau-bord",
			'sm'=>"home",
		));
    }
    public function contractAction(Request $request)
    {
		$this->removeSession();
		$em = $this->getDoctrine()->getManager();
		$ContractEntity = $em->getRepository('BoAdminBundle:Contracts');
		$oTCEntity =$em->getRepository('BoAdminBundle:Agenda');
		$this->setUserLocale($request->getLocale());
		$user=$this->getTokenUser();
		$aStudents = $sTraining=$aTeacherSchedule=$oContract=$aGroup=$oGroup=null;
		$employee=$user?$user->getEmployee():null;	
		if($employee!=null){
			$oEmTeacher=$em->getRepository('BoAdminBundle:Teachers');
			$aContract = $ContractEntity->getTeacherContracts($employee);//A faire getActiveContract
			//$aTraining[$oContract->getId()] = $em->getRepository('BoAdminBundle:Training')->findBy(array('contracts'=>$oContract));
			if(count($aContract)==0){
				$aGroup = $em->getRepository('BoAdminBundle:Group')->getEmployeeGroup($employee);
				if(isset($aGroup[0])){
					$oGroup=$aGroup[0];
					$aContract = $em->getRepository('BoAdminBundle:Contracts')->findByGroup($oGroup);
					if(count($aContract)>1) $aStudents = $em->getRepository('BoAdminBundle:Students')->StudentAndContractKey($aContract);
					else $aStudents = $em->getRepository('BoAdminBundle:Students')->findStudents($aContract);	
					//$aTeacherSchedule = $oTCEntity->getGroupTeacherScheduleBis($oGroup,$employee);
				}
			}else{
				$aStudents = $em->getRepository('BoAdminBundle:Students')->findStudents($aContract);				
			}
			$aTeacherSchedule = $oTCEntity->getTeacherScheduleBis($aContract,$employee);
		}
		$this->setActivity("Access to dashboard >> Contracts");		
        return $this->render('BoAdminBundle:Default:contract.html.twig',array(
			'user'=>$user,
			'contracts'=> $aContract,
			'groups'=>$aGroup,
			'employee'=>$employee,
			'students'=>$aStudents,
			'schedules'=>$aTeacherSchedule,
			'pm'=>"tabeau-bord",
			'sm'=>"contracts",			
		));
    }
    /**
    * Lists all Employee entities.
    */
    public function validationAction()
    {	
		$this->removeSession();
		$this->setActivity("List view");
        	$em = $this->getDoctrine()->getManager();
        	$aEmployees = $em->getRepository('BoAdminBundle:Employee')->findBy(array(),array('name' => 'desc'));
		$employees = $em->getRepository('BoAdminBundle:Timesheet')->getListEmployee($aEmployees);
		$nb_tc = count($employees);
		//get page
		$page = $this->get('session')->get('page');
		if($page==null){
			$page=1;
			$this->get('session')->set('page',1);
		}
		//get number line per page
		$nb_cpp = $em->getRepository('BoAdminBundle:Param')->getParam("display_list_page_number",1);
		$nb_pages = ceil($nb_tc/$nb_cpp);
		$offset = $page>0?($page-1) * $nb_cpp:0;
		$employees = $em->getRepository('BoAdminBundle:Timesheet')->getListEmployee($employees,$offset,$nb_cpp);
        return $this->render('BoPayrollBundle:Validation:index.html.twig', array(
            	'employees' => $employees,
		'page' => $page, // forward current page to view,
		'nb_pages' => $nb_pages, //total number page,
		'total'=>$nb_tc, // record number.
		'nb_cpp' => $nb_cpp,// line's number to display
		'pm'=>"accounting",
		'sm'=>"ts_validation",
        ));
    }
    public function firsttimeAction(Request $request)
    {
		$user=$this->getTokenUser();
		$employee=$user?$user->getEmployee():null;	
        	$editForm = $this->createForm('App\Form\EmployeeType3', $employee);
        	$editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
			$this->updateEntity($employee);
			$this->setActivity($employee->getName()." ".$employee->getFirstname()." account is updated by this user for a first connexion");
			return $this->redirectToRoute('bo_admin_homepage');
        }
        return $this->render('BoAdminBundle:User:edit.html.twig', array(
            'employee' => $employee,
            'edit_form' => $editForm->createView(),
			'pm'=>"tabeau-bord",
			'sm'=>"home",
        ));		
	}
	public function languageAction(Request $request,$_locale)
	{
		$this->setUserLocale($_locale);
		$url = $this->getReferer();
		if(empty($url)) {
			$url = $this->container->get('router')->generate('bo_admin_homepage');
		}
		return new RedirectResponse($url);
	}
    /**
    * Displays a form to edit an existing Employee entity.
    */
    public function editAction(Request $request)
    {
		$user=$this->getTokenUser();
		$employee=$user?$user->getEmployee():null;	
        $editForm = $this->createForm('App\Form\EmployeeType3', $employee);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
			$this->updateEntity($employee);
			$this->setActivity($employee->getName()." ".$employee->getFirstname()." account is updated by this user");
			return $this->redirectToRoute('bo_admin_homepage');
        }
		if($this->get('session')->get('url')==null) $this->get('session')->set('url',$request->headers->get('referer'));
        return $this->render('BoAdminBundle:User:edit.html.twig', array(
            'employee' => $employee,
            'edit_form' => $editForm->createView(),
			'pm'=>"tabeau-bord",
			'sm'=>"home",
			'url'=>$request->headers->get('referer'),
        ));
    }
    /**
    * Maintenance inprogress.
    */
    public function maintenanceAction()
    {	
	$Mfe = $this->getRepository('BoAdminBundle:Param')->getParam("notification_message_footer",48);
        return $this->render('BoAdminBundle:Default:maintenance.html.twig', array('title'=>$this->getErrorMessageBy(24),'content'=>$this->getErrorMessageBy(23),'thank'=>$this->getErrorMessageBy(25), 'mfe'=>$Mfe
        ));
    }
    /**
    * Display administration member notification.
    */
    public function notificationAction()
    {	
        return $this->render('BoAdminBundle:Default:notification.html.twig', array(
            'employee' => $employee,
            'edit_form' => $editForm->createView(),
			'pm'=>"tabeau-bord",
			'sm'=>"home",
			'url'=>$request->headers->get('referer'),
        ));
    }
    /**
    * Display administration error message for user.
    */
    public function errorAction()
    {	
	$oUser=$this->getTokenUser();
	$employee=$user?$user->getEmployee():null;
        return $this->render('BoAdminBundle:Default:error.html.twig', array(
            'employee' => $employee,
            'error_message' => $this->getSessionMessage(),
		'pm'=>"tabeau-bord",
		'sm'=>"error",
        ));
    }
	private function setActivity($activity){
		return $this->createActivity("Admin Home",$activity);	
	}
	private function getLangBy($iNumber,$aResponse){
		if($iNumber>0){
			$oResponse = $aResponse[0];
			$lang = $oResponse->getLang();
		}else{
			$lang = "en";
		}
		return $lang;
	}
}
