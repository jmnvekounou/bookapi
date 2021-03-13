<?php

namespace App\Form;

use App\Entity\Parameters;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParametersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('reference',null,array('attr'=>array('size'=>30)))
            ->add('category',null,array('required'=>false,'attr'=>array('size'=>30)))
            ->add('name',null,array('attr'=>array('required'=>true,'size'=>30)))
            ->add('value',null,array('required'=>true,'attr'=>array('size'=>30)))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Parameters::class,
        ]);
    }
}
