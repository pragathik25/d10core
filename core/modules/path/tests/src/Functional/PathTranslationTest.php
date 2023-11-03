<?php

namespace Drupal\Tests\path\Functional;

use Drupal\Core\Language\LanguageInterface;

/**
 * Tests alias langcode for multilingual nodes.
 *
 * @group path
 */
class PathTranslationTest extends PathTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'path',
    'locale',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and log in user.
    $permissions = [
      'access administration pages',
      'administer content translation',
      'administer content types',
      'administer languages',
      'administer url aliases',
      'create content translations',
      'create page content',
      'create url aliases',
      'edit any page content',
      'translate any entity',
    ];
    $user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($user);

    // Enable French language.
    $edit = [
      'predefined_langcode' => 'fr',
    ];
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add language');
  }

  /**
   * Tests the alias langcode for untranslatable node.
   */
  public function testAliasUntranslatableNode() {
    // Create a node.
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'langcode' => 'en',
    ]);
    $alias = $this->randomMachineName();

    // Edit the node to set the alias.
    $edit = [
      'path[0][alias]' => '/' . $alias,
    ];
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm($edit, 'Save');

    // Tests that the alias works.
    $this->drupalGet($alias);
    $this->assertSession()->responseContains($node->body->value);

    // Tests that the alias was saved with 'und' langcode.
    $conditions = [
      'path' => '/node/' . $node->id(),
      'alias' => '/' . $alias,
    ];
    $path = \Drupal::entityTypeManager()->getStorage('path_alias')
      ->loadByProperties($conditions);
    $path = current($path);
    $this->assertEquals($path->language()->getId(),
      LanguageInterface::LANGCODE_NOT_SPECIFIED);
  }

  /**
   * Tests the alias langcode for translatable node.
   */
  public function testAliasLangcode() {
    // Tests the langcode for translatable path.
    // It should be set to the node's langcode.
    $english_alias = $this->randomMachineName();
    $french_alias = $this->randomMachineName();
    $this->doTestAliasLangcode(TRUE, $english_alias, $french_alias, 'en', 'fr');

    // Tests the langcode for untranslatable path.
    // It should be set to not specified.
    $english_alias = $this->randomMachineName();
    $not_specified = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    $this->doTestAliasLangcode(FALSE, $english_alias, $english_alias,
      $not_specified, $not_specified);
  }

  /**
   * Helper method to test aliases' langcode.
   */
  protected function doTestAliasLangcode(
    $translate_path,
    $english_alias,
    $french_alias,
    $expected_en,
    $expected_fr
  ) {
    // Enable translation for page nodes.
    $edit = [
      'entity_types[node]' => 1,
      'settings[node][page][translatable]' => 1,
      'settings[node][page][fields][path]' => $translate_path,
      'settings[node][page][fields][body]' => 1,
      'settings[node][page][settings][language][language_alterable]' => 1,
    ];
    $this->drupalGet('admin/config/regional/content-language');
    $this->submitForm($edit, 'Save configuration');

    // Clear caches.
    \Drupal::entityTypeManager()->clearCachedDefinitions();

    // Create a node.
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $english_node = $this->drupalCreateNode([
      'type' => 'page',
      'langcode' => 'en',
    ]);

    // Edit the node to set the alias.
    $edit_en = [
      'path[0][alias]' => '/' . $english_alias,
    ];
    $this->drupalGet('node/' . $english_node->id() . '/edit');
    $this->submitForm($edit_en, 'Save');

    // Translate the node into French.
    $edit_fr = [
      'title[0][value]' => $this->randomMachineName(),
      'body[0][value]' => $this->randomMachineName(),
      'path[0][alias]' => '/' . $french_alias,
    ];
    $this->drupalGet('node/' . $english_node->id() . '/translations/add/en/fr');
    $this->submitForm($edit_fr, 'Save (this translation)');

    // Clear the path lookup cache.
    $this->container->get('path_alias.manager')->cacheClear();

    // Languages are cached on many levels, and we need to clear those caches.
    $this->container->get('language_manager')->reset();
    $this->rebuildContainer();

    // Ensure the node was created.
    $node_storage->resetCache([$english_node->id()]);
    $english_node = $node_storage->load($english_node->id());
    $this->assertTrue($english_node->hasTranslation('fr'));
    $french_translation = $english_node->getTranslation('fr');

    // Tests that both aliases work.
    $this->drupalGet($edit_en['path[0][alias]']);
    $this->assertSession()->responseContains($english_node->body->value);
    $this->drupalGet('fr' . $edit_fr['path[0][alias]']);
    $this->assertSession()->responseContains($french_translation->body->value);

    // Tests that the English alias was saved with the expected langcode.
    $conditions = [
      'path' => '/node/' . $english_node->id(),
      'alias' => $edit_en['path[0][alias]'],
    ];
    $path_en = \Drupal::entityTypeManager()->getStorage('path_alias')
      ->loadByProperties($conditions);
    $path_en = current($path_en);
    $this->assertEquals($path_en->language()->getId(), $expected_en);

    // Tests that the French alias was saved with the expected langcode.
    $conditions = [
      'path' => '/node/' . $french_translation->id(),
      'alias' => $edit_fr['path[0][alias]'],
    ];
    $path_fr = \Drupal::entityTypeManager()->getStorage('path_alias')
      ->loadByProperties($conditions);
    $path_fr = current($path_fr);
    $this->assertEquals($path_fr->language()->getId(), $expected_fr);
  }

}
