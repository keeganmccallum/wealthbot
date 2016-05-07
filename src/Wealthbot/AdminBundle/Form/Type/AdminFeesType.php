<?php
/**
 * Created by JetBrains PhpStorm.
 * User: amalyuhin
 * Date: 10.10.12
 * Time: 18:27
 * To change this template use File | Settings | File Templates.
 */

namespace Wealthbot\AdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Wealthbot\UserBundle\Entity\User;

class AdminFeesType extends AbstractType
{
    /** @var \Wealthbot\UserBundle\Entity\User $owner */
    private $owner;

    /** @var \Wealthbot\UserBundle\Entity\User $appointedUser */
    private $appointedUser;

    public function __construct(User $owner, User $appointedUser = null)
    {
        $this->owner = $owner;
        $this->appointedUser = $appointedUser;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('fees', 'collection', [
            'type' => new FeeFormType($this->owner),
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => true,
            'prototype_name' => 'fee__name__',
            'by_reference' => false,
           // // 'property_path' => '',
        ]);

        $builder->addEventListener(FormEvents::SUBMIT, [$this, 'onSubmit']);
    }

    public function onSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $fees = $form->get('fees')->getData();
        $maxTopTierValue = 1000000000000;

        $bottom = 0;
        $hasTopTier = false;

        foreach ($fees as $key => $fee) {
            $formBottom = round($form->get('fees')->get($key)->get('tier_bottom')->getData(), 2);

            if ($formBottom !== $bottom) {
                if (round($bottom) === $maxTopTierValue) {
                    $form->get('fees')->get($key)->get('tier_top')->addError(
                        new FormError('You already have specified final tier.')
                    );
                    break;
                } else {
                    $form->get('fees')->get($key)->get('tier_bottom')->addError(
                        new FormError('Tier bottom should be %bottom%, %form_bottom% given.', [
                            '%bottom%' => number_format($bottom, 2),
                            '%form_bottom%' => number_format($formBottom, 2),
                        ])
                    );
                }
            }

            if ($formBottom >= $fee->getTierTop()) {
                $form->get('fees')->get($key)->get('tier_top')->addError(
                    new FormError('This value must be greater than tier bottom.')
                );
            }

            if ($fee->getFeeWithRetirement() >= 1) {
                $form->get('fees')->get($key)->get('fee_with_retirement')->addError(
                    new FormError('Fee must be greater than 0 and less then 1 (example : 0.0125).')
                );
            }

            if ($fee->getFeeWithoutRetirement() >= 1) {
                $form->get('fees')->get($key)->get('fee_without_retirement')->addError(
                    new FormError('Fee must be greater than 0 and less then 1 (example : 0.0125).')
                );
            }

            $bottom = round(($fee->getTierTop() + 0.01), 2);
            if (round($fee->getTierTop()) === $maxTopTierValue) {
                $hasTopTier = true;
            }

            if (null !== $this->appointedUser) {
                $fee->setAppointedUser($this->appointedUser);
            }
        }

        if (!$hasTopTier) {
            $form->addError(new FormError('You should specify final tier', [
                '%bottom%' => $bottom,
            ]));
        }
    }

    public function getBlockPrefix()
    {
        return 'rx_admin_fee_type';
    }
}
