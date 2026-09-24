<?php

namespace Alamia360\Tests\Unit\Semantic;

use Alamia360\Facades\Alamia360;
use Alamia360\Semantic\EntityDefinition;
use Alamia360\Semantic\SemanticRegistry;
use Alamia360\Tests\TestCase;

class SemanticRegistrationTest extends TestCase
{
    public function test_entity_can_be_registered_and_retrieved(): void
    {
        $entity = Alamia360::entity('appointment')
            ->describe('A scheduled appointment');

        $this->assertInstanceOf(EntityDefinition::class, $entity);

        $registry = $this->app->make(SemanticRegistry::class);
        $this->assertTrue($registry->hasEntity('appointment'));
        $this->assertSame($entity, $registry->getEntity('appointment'));
    }

    public function test_entity_description_is_stored(): void
    {
        Alamia360::entity('patient')->describe('A registered patient in the system');

        $entity = $this->app->make(SemanticRegistry::class)->getEntity('patient');
        $this->assertEquals('A registered patient in the system', $entity->description());
    }

    public function test_entity_attributes_chain_fluently(): void
    {
        $entity = Alamia360::entity('lab_result')
            ->attribute('status', ['type' => 'string', 'label' => 'Status'])
            ->attribute('value', ['type' => 'float', 'label' => 'Result Value']);

        $attrs = $entity->attributes();
        $this->assertArrayHasKey('status', $attrs);
        $this->assertArrayHasKey('value', $attrs);
        $this->assertEquals('string', $attrs['status']['type']);
    }

    public function test_entity_relationships_are_stored(): void
    {
        $entity = Alamia360::entity('appointment')
            ->relatesTo('patient', 'patient', 'belongsTo')
            ->relatesTo('doctor', 'doctor', 'belongsTo');

        $rels = $entity->relationships();
        $this->assertArrayHasKey('patient', $rels);
        $this->assertArrayHasKey('doctor', $rels);
        $this->assertEquals('patient', $rels['patient']->relatedEntity());
        $this->assertEquals('belongsTo', $rels['patient']->type());
    }

    public function test_entity_resolver_can_be_set_and_called(): void
    {
        $entity = Alamia360::entity('invoice')
            ->resolveUsing(fn ($id) => ['id' => $id, 'amount' => 100.00]);

        $result = $entity->resolve(42);
        $this->assertEquals(['id' => 42, 'amount' => 100.00], $result);
    }

    public function test_entity_without_resolver_throws(): void
    {
        $entity = Alamia360::entity('orphan');
        $this->expectException(\RuntimeException::class);
        $entity->resolve(1);
    }

    public function test_unknown_entity_throws_invalid_argument_exception(): void
    {
        $registry = $this->app->make(SemanticRegistry::class);
        $this->expectException(\InvalidArgumentException::class);
        $registry->getEntity('non_existent');
    }

    public function test_all_entities_returns_registered_entities(): void
    {
        Alamia360::entity('entity_a');
        Alamia360::entity('entity_b');

        $all = $this->app->make(SemanticRegistry::class)->allEntities();
        $this->assertArrayHasKey('entity_a', $all);
        $this->assertArrayHasKey('entity_b', $all);
    }
}
