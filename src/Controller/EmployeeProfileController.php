<?php

namespace App\Controller;

use App\Entity\EmployeeProfile;
use App\Form\EmployeeProfileType;
use App\Repository\EmployeeProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/employee/profile")
 */
class EmployeeProfileController extends AbstractController
{
    /**
     * @Route("/", name="employee_profile_index", methods={"GET"})
     */
    public function index(EmployeeProfileRepository $employeeProfileRepository): Response
    {
        return $this->render('employee_profile/index.html.twig', [
            'employee_profiles' => $employeeProfileRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="employee_profile_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $employeeProfile = new EmployeeProfile();
        $form = $this->createForm(EmployeeProfileType::class, $employeeProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($employeeProfile);
            $entityManager->flush();

            return $this->redirectToRoute('employee_profile_index');
        }

        return $this->render('employee_profile/new.html.twig', [
            'employee_profile' => $employeeProfile,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="employee_profile_show", methods={"GET"})
     */
    public function show(EmployeeProfile $employeeProfile): Response
    {
        return $this->render('employee_profile/show.html.twig', [
            'employee_profile' => $employeeProfile,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="employee_profile_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, EmployeeProfile $employeeProfile): Response
    {
        $form = $this->createForm(EmployeeProfileType::class, $employeeProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('employee_profile_index');
        }

        return $this->render('employee_profile/edit.html.twig', [
            'employee_profile' => $employeeProfile,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="employee_profile_delete", methods={"DELETE"})
     */
    public function delete(Request $request, EmployeeProfile $employeeProfile): Response
    {
        if ($this->isCsrfTokenValid('delete'.$employeeProfile->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($employeeProfile);
            $entityManager->flush();
        }

        return $this->redirectToRoute('employee_profile_index');
    }
}
