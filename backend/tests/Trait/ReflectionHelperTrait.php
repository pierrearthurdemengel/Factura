<?php

namespace App\Tests\Trait;

/**
 * Trait utilitaire pour les tests necessitant l'acces a des proprietes privees.
 *
 * Centralise l'usage de ReflectionProperty afin d'eviter les alertes S3011
 * dans chaque fichier de test.
 *
 * @SuppressWarnings("S3011")
 */
trait ReflectionHelperTrait
{
    /**
     * Definit la valeur d'une propriete privee ou protegee sur un objet.
     */
    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object::class, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
