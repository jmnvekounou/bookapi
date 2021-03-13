<?php

namespace App\Controller;

use App\Entity\EmployeeStatus;
use App\Form\EmployeeStatusType;
use App\Repository\EmployeeStatusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/employee/status")
 */
class EmployeeStatusController extends AbstractController
{
    /**
     * @Route("/", name="employee_status_index", methods={"GET"})
     */
    public function index(EmployeeStatusRepository $employeeStatusRepository): Response
    {
        return $this->render('employee_status/index.html.twig', [
            'employee_statuses' => $employeeStatusRepository->findAll(),
        ]);
    }

    /**
     * @Route("/new", name="employee_status_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $employeeStatus = new EmployeeStatus();
        $form = $this->createForm(EmployeeStatusType::class, $employeeStatus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($employeeStatus);
            $entityManager->flush();

            return $this->redirectToRoute('employee_status_index');
        }

        return $this->render('employee_status/new.html.twig', [
            'employee_status' => $employeeStatus,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="employee_status_show", methods={"GET"})
     */
    public function show(EmployeeStatus $employeeStatus): Response
    {
        return $this->render('employee_status/show.html.twig', [
            'employee_status' => $employeeStatus,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="employee_status_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, EmployeeStatus $employeeStatus): Response
    {
        $form = $this->createForm(EmployeeStatusType::class, $employeeStatus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('employee_status_index');
        }

        return $this->render('employee_status/edit.html.twig', [
            'employee_status' => $employeeStatus,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="employee_status_delete", methods={"DELETE"})
     */
    public function delete(Request $request, EmployeeStatus $employeeStatus): Response
    {
        if ($this->isCsrfTokenValid('delete'.$employeeStatus->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($employeeStatus);
            $entityManager->flush();
        }

        return $this->redirectToRoute('employee_status_index');
    }
}
