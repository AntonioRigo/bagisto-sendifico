<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the "Free shipping" product toggle to the Shipping group of every attribute family.
     */
    public function up(): void
    {
        if (DB::table('attributes')->where('code', 'free_shipping')->exists()) {
            return;
        }

        $now = now();

        $attributeId = DB::table('attributes')->insertGetId([
            'code' => 'free_shipping',
            'admin_name' => 'Free shipping',
            'type' => 'boolean',
            'position' => (int) DB::table('attributes')->max('position') + 1,
            'is_required' => 0,
            'is_unique' => 0,
            'is_filterable' => 0,
            'is_comparable' => 0,
            'is_configurable' => 0,
            'is_user_defined' => 0,
            'is_visible_on_front' => 0,
            'value_per_locale' => 0,
            'value_per_channel' => 0,
            'default_value' => 0,
            'enable_wysiwyg' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table('locales')->pluck('code') as $locale) {
            DB::table('attribute_translations')->insert([
                'attribute_id' => $attributeId,
                'locale' => $locale,
                'name' => trans('sendifico::app.admin.attribute.free-shipping', [], $locale),
            ]);
        }

        $groupIds = DB::table('attribute_groups')->where('code', 'shipping')->pluck('id');

        foreach ($groupIds as $groupId) {
            DB::table('attribute_group_mappings')->insert([
                'attribute_id' => $attributeId,
                'attribute_group_id' => $groupId,
                'position' => (int) DB::table('attribute_group_mappings')->where('attribute_group_id', $groupId)->max('position') + 1,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attributes')->where('code', 'free_shipping')->delete();
    }
};
