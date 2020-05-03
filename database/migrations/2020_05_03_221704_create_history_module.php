<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Uccello\Core\Database\Migrations\Migration;
use Uccello\Core\Models\Module;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Tab;
use Uccello\Core\Models\Block;
use Uccello\Core\Models\Field;
use Uccello\Core\Models\Filter;
use Uccello\Core\Models\Relatedlist;
use Uccello\Core\Models\Link;

class CreateHistoryModule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->createTable();
        $module = $this->createModule();
        $this->activateModuleOnDomains($module);
        $this->createTabsBlocksFields($module);
        $this->createFilters($module);
        $this->createRelatedLists($module);
        $this->createLinks($module);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop table
        Schema::dropIfExists($this->tablePrefix . 'histories');

        // Delete module
        Module::where('name', 'history')->forceDelete();
    }

    protected function initTablePrefix()
    {
        $this->tablePrefix = '';

        return $this->tablePrefix;
    }

    protected function createTable()
    {
        Schema::create($this->tablePrefix . 'histories', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('model_uuid');
            $table->unsignedInteger('user_id');
            $table->string('description')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('domain_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('domain_id')->references('id')->on('uccello_domains');
            $table->foreign('model_uuid')->references('id')->on('uccello_entities');

        });
    }

    protected function createModule()
    {
        $module = Module::create([
            'name' => 'history',
            'icon' => 'history',
            'model_class' => 'JeromeSavin\UccelloHistory\Models\History',
            'data' => json_decode('{"package":"jerome-savin\/uccello-history","admin":true}')
        ]);

        return $module;
    }

    protected function activateModuleOnDomains($module)
    {
        $domains = Domain::all();
        foreach ($domains as $domain) {
            $domain->modules()->attach($module);
        }
    }

    protected function createTabsBlocksFields($module)
    {
        // Tab tab.main
        $tab = Tab::create([
            'module_id' => $module->id,
            'label' => 'tab.main',
            'icon' => null,
            'sequence' => $module->tabs()->count(),
            'data' => null
        ]);

        // Block block.general
        $block = Block::create([
            'module_id' => $module->id,
            'tab_id' => $tab->id,
            'label' => 'block.general',
            'icon' => null,
            'sequence' => $tab->blocks()->count(),
            'data' => null
        ]);

        // Field user
        Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'name' => 'user',
            'uitype_id' => uitype('entity')->id,
            'displaytype_id' => displaytype('everywhere')->id,
            'sequence' => $block->fields()->count(),
            'data' => json_decode('{"rules":"required","module":"user"}')
        ]);

        // Field description
        Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'name' => 'description',
            'uitype_id' => uitype('text')->id,
            'displaytype_id' => displaytype('detail')->id,
            'sequence' => $block->fields()->count(),
            'data' => json_decode('{"large":true}')
        ]);

        // Field created_at
        Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'name' => 'created_at',
            'uitype_id' => uitype('datetime')->id,
            'displaytype_id' => displaytype('detail')->id,
            'sequence' => $block->fields()->count(),
            'data' => null
        ]);

        // Field model_uuid
        Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'name' => 'model_uuid',
            'uitype_id' => uitype('text')->id,
            'displaytype_id' => displaytype('detail')->id,
            'sequence' => $block->fields()->count(),
            'data' => null
        ]);

    }

    protected function createFilters($module)
    {
        // Filter
        Filter::create([
            'module_id' => $module->id,
            'domain_id' => null,
            'user_id' => null,
            'name' => 'filter.all',
            'type' => 'list',
            'columns' => [ 'user', 'description', 'created_at' ],
            'conditions' => null,
            'order' => null,
            'is_default' => true,
            'is_public' => false,
            'data' => [ 'readonly' => true ]
        ]);

    }

    protected function createRelatedLists($module)
    {
        $opportunity_module = Module::where('name', 'opportunity')->first();


        Relatedlist::create([
            'module_id' => $opportunity_module->id,
            'related_module_id' => $module->id,
            'related_field_id' => Field::where('name', 'model_uuid')->first()->id,
            'label' => 'relatedlist.histories',
            'type' => 'n-1',
            'method' => 'getDependentList',
            'sequence' => $opportunity_module->relatedlists()->count(),
            'data' => null
        ]);  
    }

    protected function createLinks($module)
    {
    }
}