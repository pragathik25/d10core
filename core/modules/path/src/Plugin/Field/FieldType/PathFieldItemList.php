<?php

namespace Drupal\path\Plugin\Field\FieldType;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Represents a configurable entity path field.
 */
class PathFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    // Default the langcode to the current language if this is a new entity or
    // there is no alias for an existent entity.
    $value = ['langcode' => $this->getLangcode()];

    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      /** @var \Drupal\path_alias\AliasRepositoryInterface $path_alias_repository */
      $path_alias_repository = \Drupal::service('path_alias.repository');

      if ($path_alias = $path_alias_repository->lookupBySystemPath('/' . $entity->toUrl()->getInternalPath(), $this->getLangcode())) {
        $value = [
          'alias' => $path_alias['alias'],
          'pid' => $path_alias['id'],
          'langcode' => $path_alias['langcode'],
        ];

        // Set the langcode to not specified for untranslatable fields.
        if (!$entity->isTranslatable() || !$this->getFieldDefinition()->isTranslatable()) {
          $value['langcode'] = LanguageInterface::LANGCODE_NOT_SPECIFIED;
        }
      }
    }

    $this->list[0] = $this->createItem(0, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultAccess($operation = 'view', AccountInterface $account = NULL) {
    if ($operation == 'view') {
      return AccessResult::allowed();
    }
    return AccessResult::allowedIfHasPermissions($account, ['create url aliases', 'administer url aliases'], 'OR')->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // Delete all aliases associated with this entity in the current language.
    $entity = $this->getEntity();

    // If neither the path field nor entity being deleted is translatable,
    // delete alias with LANGCODE_NOT_SPECIFIED.
    if (!$entity->isTranslatable() || !$this->getFieldDefinition()->isTranslatable()) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    else {
      $langcode = $entity->language()->getId();
    }

    $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
    $conditions = [
      'path' => '/' . $entity->toUrl()->getInternalPath(),
      'langcode' => $langcode,
    ];
    $entity_langcode = $entity->language()->getId();
    $original_entity_langcode = $entity->getUntranslated()->language()->getId();
    // If the entity being deleted is not translated, delete the path alias
    // with the langcode LANGCODE_NOT_SPECIFIED.
    if ($entity_langcode == $original_entity_langcode) {
      $conditions['langcode'] = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    $entities = $path_alias_storage->loadByProperties($conditions);
    $path_alias_storage->delete($entities);
  }

}
