<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IndustryTemplate;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
{
    /**
     * List all available templates
     */
    public function index()
    {
        $templates = IndustryTemplate::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'description' => $template->description,
                    'icon' => $template->icon,
                    'usage_count' => $template->usage_count,
                    'features' => [
                        'categories_count' => count($template->categories ?? []),
                        'products_count' => count($template->products ?? []),
                        'accounts_count' => count($template->chart_of_accounts ?? []),
                        'reports_count' => count($template->reports ?? []),
                    ],
                ];
            });

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Get specific template details
     */
    public function show($slug)
    {
        $template = IndustryTemplate::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'icon' => $template->icon,
                'categories' => $template->categories,
                'sample_products' => array_slice($template->products ?? [], 0, 5),
                'chart_of_accounts' => $template->chart_of_accounts,
                'settings' => $template->settings,
                'reports' => $template->reports,
                'usage_count' => $template->usage_count,
            ],
        ]);
    }

    /**
     * Apply template to tenant
     */
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:industry_templates,id',
            'include_products' => 'boolean',
            'include_categories' => 'boolean',
            'include_accounts' => 'boolean',
        ]);

        $template = IndustryTemplate::findOrFail($validated['template_id']);
        $tenant = $request->user()->tenant ?? tenant();

        DB::beginTransaction();
        try {
            $tenant->run(function () use ($template, $validated) {
                $categoryMap = [];

                // Import categories
                if (($validated['include_categories'] ?? true) && $template->categories) {
                    foreach ($template->categories as $index => $categoryData) {
                        $category = \App\Models\Category::create($categoryData);
                        $categoryMap[$index + 1] = $category->id;
                    }
                }

                // Import products
                if (($validated['include_products'] ?? true) && $template->products) {
                    foreach ($template->products as $productData) {
                        // Map category ID
                        if (isset($productData['category_id']) && isset($categoryMap[$productData['category_id']])) {
                            $productData['category_id'] = $categoryMap[$productData['category_id']];
                        }

                        \App\Models\Product::create($productData);
                    }
                }

                // Import chart of accounts (if you have an Account model)
                if (($validated['include_accounts'] ?? true) && $template->chart_of_accounts) {
                    // Implementation depends on your accounting module structure
                }

                // Apply settings
                if ($template->settings) {
                    // Store tenant-specific settings
                    foreach ($template->settings as $key => $value) {
                        // You might want to create a TenantSetting model
                        // TenantSetting::updateOrCreate(['key' => $key], ['value' => $value]);
                    }
                }
            });

            $template->recordUsage();

            // Update tenant metadata
            $tenant->update([
                'industry_template' => $template->slug,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Template applied successfully',
                'data' => [
                    'template' => $template->name,
                    'categories_imported' => count($template->categories ?? []),
                    'products_imported' => count($template->products ?? []),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to apply template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get onboarding checklist for template
     */
    public function checklist(Request $request, $slug)
    {
        $template = IndustryTemplate::where('slug', $slug)->firstOrFail();
        $tenant = $request->user()->tenant ?? tenant();

        $checklist = [];

        $tenant->run(function () use (&$checklist, $template) {
            $categoriesCount = \App\Models\Category::count();
            $productsCount = \App\Models\Product::count();
            $customersCount = \App\Models\Customer::count();
            $salesCount = \App\Models\Sale::count();

            $checklist = [
                [
                    'task' => 'Import categories',
                    'completed' => $categoriesCount > 0,
                    'count' => $categoriesCount,
                    'target' => count($template->categories ?? []),
                ],
                [
                    'task' => 'Import products',
                    'completed' => $productsCount > 0,
                    'count' => $productsCount,
                    'target' => count($template->products ?? []),
                ],
                [
                    'task' => 'Add first customer',
                    'completed' => $customersCount > 0,
                    'count' => $customersCount,
                    'target' => 1,
                ],
                [
                    'task' => 'Record first sale',
                    'completed' => $salesCount > 0,
                    'count' => $salesCount,
                    'target' => 1,
                ],
            ];
        });

        $completionRate = collect($checklist)
            ->where('completed', true)
            ->count() / count($checklist) * 100;

        return response()->json([
            'data' => [
                'checklist' => $checklist,
                'completion_rate' => round($completionRate),
                'time_to_value' => $completionRate >= 75 ? 'Ready to operate!' : 'Almost there!',
            ],
        ]);
    }
}
