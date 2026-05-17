<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FormResource;
use App\Http\Resources\Api\FormSchemaResource;
use App\Models\Form;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Forms
 *
 * Public endpoints for browsing forms and fetching form schemas.
 * No authentication required.
 */
class FormController extends Controller
{
    /**
     * List active forms
     *
     * Returns a paginated list of all active forms.
     *
     * @queryParam page integer The page number. Example: 1
     *
     * @response 200 scenario="success" {"data":[{"uuid":"9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c","name":"Contact Us","slug":"contact-us","description":"A simple contact form","is_active":true,"version":1,"created_at":"2026-04-08T00:00:00.000000Z"}],"links":{},"meta":{"current_page":1,"last_page":1,"per_page":15,"total":1}}
     */
    public function index(): AnonymousResourceCollection
    {
        return FormResource::collection(Form::active()->paginate(15));
    }

    /**
     * Get form by UUID
     *
     * Returns a single form identified by its UUID.
     *
     * @urlParam uuid string required The UUID of the form. Example: 9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c
     *
     * @response 200 scenario="success" {"data":{"uuid":"9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c","name":"Contact Us","slug":"contact-us","description":"A simple contact form","is_active":true,"version":1,"created_at":"2026-04-08T00:00:00.000000Z"}}
     * @response 404 scenario="not found" {"message":"Not Found"}
     */
    public function show(string $uuid): FormResource
    {
        $form = Form::where('uuid', $uuid)->firstOrFail();

        return new FormResource($form);
    }

    /**
     * Get form by slug
     *
     * Returns a single active form identified by its slug.
     *
     * @urlParam slug string required The slug of the form. Example: contact-us
     *
     * @response 200 scenario="success" {"data":{"uuid":"9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c","name":"Contact Us","slug":"contact-us","description":"A simple contact form","is_active":true,"version":1,"created_at":"2026-04-08T00:00:00.000000Z"}}
     * @response 404 scenario="not found" {"message":"Not Found"}
     */
    public function showBySlug(string $slug): FormResource
    {
        $form = Form::where('slug', $slug)->active()->firstOrFail();

        return new FormResource($form);
    }

    /**
     * Get form schema
     *
     * Returns the complete form schema including steps, fields, field types, options, conditions, and entities.
     * This is the primary endpoint for rendering a form on the frontend.
     *
     * @urlParam uuid string required The UUID of the form. Example: 9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c
     *
     * @response 200 scenario="success" {"data":{"uuid":"9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c","name":"Contact Us","slug":"contact-us","description":"A simple contact form","is_active":true,"version":1,"created_at":"2026-04-08T00:00:00.000000Z","steps":[{"id":1,"step_number":1,"title":"Step 1","description":null,"is_visible":true,"meta":null,"fields":[{"id":1,"code":"email","label":"Email","placeholder":null,"description":null,"is_required":true,"is_repeatable":false,"order":1,"field_type":{"name":"text","component":"text-input"},"options":[],"conditions":[]}]}],"entities":[]}}
     * @response 404 scenario="not found" {"message":"Not Found"}
     */
    public function schema(string $uuid): FormSchemaResource
    {
        $form = Form::where('uuid', $uuid)
            ->with([
                'steps.fields.fieldType',
                'steps.fields.options',
                'steps.fields.conditions.dependsOnField',
                'entities',
            ])
            ->firstOrFail();

        return new FormSchemaResource($form);
    }
}
