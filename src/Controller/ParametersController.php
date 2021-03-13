<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\Parameters;
use App\Form\ParametersType;
use App\Repository\ParametersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @Route("/parameters")
 */
class ParametersController extends AbstractController
{
    
    /**
     * @Route("/", name="parameters_index", methods={"GET"})
     */
    public function index(ParametersRepository $parametersRepository): Response
    {
/*
        $employee = new Employee();
		$employee->setSexe("Masculin");
		$employee->setName("Dortoir");
		$employee->setFirstname("Maxime");
		$employee->setEmail("maxime.dortoir@gmail.com");
		$employee->setMobileNumber("819 230 0667");
		$employee->setBirthdayDate(new \DateTime("1974-12-04"));
		$employee->setHiringDate(new \DateTime("2020-12-04"));

		$encoders   = [new XmlEncoder(), new JsonEncoder()];
		$normalizer = [new ObjectNormalizer()];
		$serializer = new Serializer($normalizer, $encoders);

		$jsonContent = $serializer->serialize($employee, 'json');

		echo $jsonContent;
		die;*/

        return $this->render('parameters/index.html.twig', [
            'parameters' => $parametersRepository->findAll(),
            'form'       => $this->getCreateForm()->createView(),
            'pm'         => $this->getMenu()[0],
            'sm'         => $this->getMenu()[1],

        ]);
    }

    /**
     * @Route("/new", name="parameters_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $parameter = new Parameters();
        $form = $this->createForm(ParametersType::class, $parameter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($parameter);
            $entityManager->flush();

            return $this->redirectToRoute('parameters_index');
        }

        return $this->render('parameters/new.html.twig', [
            'parameter' => $parameter,
            'form'      => $form->createView(),
            'pm'        => $this->getMenu()[0],
            'sm'        => $this->getMenu()[1],
        ]);
    }

    /**
     * @Route("/{id}", name="parameters_show", methods={"GET"})
     */
    public function show(Parameters $parameter): Response
    {
        return $this->render('parameters/show.html.twig', [
            'parameter' => $parameter,
            'pm'         => $this->getMenu()[0],
            'sm'         => $this->getMenu()[1],
        ]);
    }

    /**
     * @Route("/{id}/edit", name="parameters_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Parameters $parameter): Response
    {
        $form = $this->createForm(ParametersType::class, $parameter);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('parameters_index');
        }

        return $this->render('parameters/edit.html.twig', [
            'parameter' => $parameter,
            'form' => $form->createView(),
            'pm'         => $this->getMenu()[0],
            'sm'         => $this->getMenu()[1],
        ]);
    }

    /**
     * @Route("/{id}", name="parameters_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Parameters $parameter): Response
    {
        if ($this->isCsrfTokenValid('delete'.$parameter->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($parameter);
            $entityManager->flush();
        }

        return $this->redirectToRoute('parameters_index');
    }

    /**
     * @Route("/search", name="parameters_search", methods={"GET","POST"})
     */
    public function searchAction(Request $request, ParametersRepository $parametersRepository) : Response 
    {

		if($request->isXmlHttpRequest())
		{	
			$description = $request->request->get('description');	
		}

		$this->get('session')->set('search',$description);

		$aResult = $parametersRepository->search($description);

		return $this->render('parameters/search.html.twig', array(
			'params'=>$aResult,
			'count'=>count($aResult),
			'description'=>$description,
		));				
    }

    /**
     * @Route("/page/search", name="parameters_pagesearch", methods={"GET","POST"})
     */
    public function pagesearchAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $params = $em->getRepository('BoAdminBundle:Param')->findAll();
		$nb_tc = count($params);
		if($request->isXmlHttpRequest())
		{	
			$page = $request->request->get('data');
		}
		$this->get('session')->set('page',$page);
		//get number line per page
		$nb_cpp = $em->getRepository('BoAdminBundle:Param')->getParam("display_list_page_number",1);
		$nb_pages = ceil($nb_tc/$nb_cpp);
		$offset = $page>0?($page-1) * $nb_cpp:0;
		$aParam = $em->getRepository('BoAdminBundle:Param')->findBy(array(),array('id'=>'desc'),$nb_cpp,$offset);
        return $this->render('BoAdminBundle:Param:tbliste.html.twig', array(
            'params' => $aParam,
			'page' => $page, // On transmet à la vue la page courante,
        ));
    }

    /**
     * GetCreateForm
     */
    private function getCreateForm()
    {
        return $this->createForm(ParametersType::class, new Parameters());
    }

    /**
     * GetMenu
     */
    private function getMenu()
    {
        return ["setting", "setting"];
    }
}
