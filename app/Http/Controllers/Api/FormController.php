<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FormResource;
use App\Http\Resources\Api\FormSchemaResource;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Forms
 *
 * Endpoints for browsing active public forms, listing the authenticated owner's forms, and fetching form schemas.
 */
class FormController extends Controller
{
    /**
     * List forms
     *
     * Returns active public forms when called without a bearer token, or all forms owned by the authenticated token user when called with a bearer token carrying the `forms:read` ability.
     *
     * @queryParam page integer The page number. Example: 1
     * @queryParam per_page integer Number of forms per page (1-100, default 15). Example: 15
     *
     * @response 200 scenario="success" {"data":[{"uuid":"9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c","name":"Contact Us","slug":"contact-us","description":"A simple contact form","is_active":true,"version":1,"created_at":"2026-04-08T00:00:00.000000Z"}],"links":{},"meta":{"current_page":1,"last_page":1,"per_page":15,"total":1}}
     * @response 401 scenario="invalid token" {"message":"Unauthenticated."}
     * @response 403 scenario="missing forms:read ability" {"message":"Invalid ability provided."}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        if ($user = $request->user()) {
            return FormResource::collection(
                Form::where('user_id', $user->id)
                    ->latest('id')
                    ->paginate($perPage)
            );
        }

        return FormResource::collection(Form::active()->paginate($perPage));
    }

    /**
     * Get form by UUID
     *
     * Returns a single active form identified by its UUID.
     * Inactive forms return 404.
     *
     * @urlParam uuid string required The UUID of the form. Example: 9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c
     *
     * @response 200 scenario="success" {"data":{"uuid":"9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c","name":"Contact Us","slug":"contact-us","description":"A simple contact form","is_active":true,"version":1,"created_at":"2026-04-08T00:00:00.000000Z"}}
     * @response 404 scenario="not found" {"message":"Not Found"}
     */
    public function show(string $uuid): FormResource
    {
        $form = Form::where('uuid', $uuid)->active()->firstOrFail();

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
     * Only active forms are served; inactive forms return 404.
     *
     * @urlParam uuid string required The UUID of the form. Example: 9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c
     *
     * @response 200 scenario="success" {"data":{"uuid":"9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c","name":"Contact Us","slug":"contact-us","description":"A simple contact form","is_active":true,"version":1,"created_at":"2026-04-08T00:00:00.000000Z","steps":[{"id":1,"step_number":1,"title":"Step 1","description":null,"is_visible":true,"meta":null,"fields":[{"id":1,"code":"email","label":"Email","placeholder":null,"description":null,"is_required":true,"is_repeatable":false,"order":1,"field_type":{"name":"text","component":"text-input"},"options":[],"conditions":[]}]}],"entities":[]}}
     * @response 404 scenario="not found" {"message":"Not Found"}
     */
    public function schema(string $uuid): FormSchemaResource
    {
        $form = Form::where('uuid', $uuid)
            ->active()
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
