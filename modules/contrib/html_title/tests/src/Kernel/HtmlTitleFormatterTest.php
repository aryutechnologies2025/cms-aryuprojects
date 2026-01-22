<?php

namespace Drupal\Tests\html_title\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test the HTML Title formatter.
 *
 * @group html_title
 */
class HtmlTitleFormatterTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'text',
    'entity_test',
    'user',
    'system',
    'html_title',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The name of the field we use for testing.
   *
   * @var string
   */
  protected $fieldName = 'field_text';

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId = 'entity_test';

  /**
   * The bundle name.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['field', 'html_title']);
    $this->installEntitySchema('entity_test');

    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'string',
      'entity_type' => $this->entityTypeId,
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => $this->entityTypeId,
      'field_name' => $this->fieldName,
      'bundle' => $this->bundle,
      'label' => 'Test text-field',
    ])->save();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityDisplayRepository = $this->container->get('entity_display.repository');
  }

  /**
   * Test html_title formatter.
   *
   * @dataProvider htmlTitleFormatterDataProvider
   */
  public function testHtmlTitleFormatter(string $expected, string $value) {
    $entity = $this->entityTypeManager->getStorage($this->entityTypeId)->create([]);
    $entity->set($this->fieldName, [
      'value' => $value,
    ]);

    $build = $this->buildEntityField($entity, $this->fieldName, 'html_title');
    $this->render($build);
    $this->assertRaw($expected);
  }

  /**
   * Data provider for testHtmlTitleFormatter().
   *
   * @see testHtmlTitleFormatter()
   */
  public static function htmlTitleFormatterDataProvider() {
    return [
      [
        'Test <sup>sup</sup>-tag',
        'Test <sup>sup</sup>-tag',
      ],
      [
        'Test p-tag',
        'Test <p>p</p>-tag',
      ],
    ];
  }

  /**
   * Helper method that builds a renderable array for a given entity field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   * @param string $formatter_type
   *   The formatter type.
   * @param array $formatter_settings
   *   The formatter settings.
   *
   * @return array
   *   The field renderable array.
   */
  protected function buildEntityField(FieldableEntityInterface $entity, string $field_name, string $formatter_type, array $formatter_settings = []): array {
    $display = $this->entityDisplayRepository->getViewDisplay($entity->getEntityTypeId(), $entity->bundle());

    $display->setComponent($field_name, [
      'type' => $formatter_type,
      'settings' => $formatter_settings,
    ]);
    $display->save();

    $build = $display->build($entity);
    return $build[$field_name];
  }

}
