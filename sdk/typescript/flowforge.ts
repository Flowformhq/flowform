// FlowForge TypeScript SDK
// Zero-dependency client for the FlowForge API

// ── Types ──────────────────────────────────────────────────────────────────

export interface Form {
  uuid: string;
  name: string;
  slug: string | null;
  description: string | null;
  is_active: boolean;
  version: number;
  created_at: string;
}

export interface FieldType {
  name: string;
  component: string;
}

export interface FieldOption {
  label: string;
  value: string;
  order: number;
}

export interface Condition {
  depends_on_field_code: string | null;
  operator: string;
  value: string | null;
  action: string;
}

export interface Field {
  id: number;
  code: string;
  label: string;
  placeholder: string | null;
  description: string | null;
  is_required: boolean;
  is_repeatable: boolean;
  order: number;
  field_type: FieldType;
  options: FieldOption[];
  conditions: Condition[];
}

export interface Step {
  id: number;
  step_number: number;
  title: string;
  description: string | null;
  is_visible: boolean;
  meta: Record<string, unknown> | null;
  fields: Field[];
}

export interface Entity {
  id: number;
  name: string;
  label: string;
  is_repeatable: boolean;
}

export interface FormSchema extends Form {
  steps: Step[];
  entities: Entity[];
}

export interface Submission {
  uuid: string;
  status: "draft" | "completed" | "abandoned";
  current_step: number;
  progress_percentage: number;
  meta: Record<string, unknown> | null;
  created_at: string;
}

export interface SubmissionDetail extends Submission {
  values: Record<string, string | null>;
}

export interface FieldState {
  field_id: number;
  field_code: string;
  is_visible: boolean;
  is_required: boolean;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links: Record<string, string | null>;
}

export interface ApiResponse<T> {
  data: T;
}

export interface StepResponse {
  current_step: number;
}

export interface FieldValue {
  field_code: string;
  value: string | null;
}

// ── Error ──────────────────────────────────────────────────────────────────

export class FlowForgeError extends Error {
  constructor(
    public status: number,
    public body: unknown,
  ) {
    super(`FlowForge API error ${status}`);
    this.name = "FlowForgeError";
  }
}

// ── Client ─────────────────────────────────────────────────────────────────

export class FlowForgeClient {
  private baseUrl: string;
  private token: string | null;

  constructor(baseUrl: string, token?: string) {
    this.baseUrl = baseUrl.replace(/\/+$/, "");
    this.token = token ?? null;
  }

  /** Update the bearer token (e.g. after login). */
  setToken(token: string): void {
    this.token = token;
  }

  // ── Forms (public) ────────────────────────────────────────────────────

  /** List active forms (paginated). */
  async getForms(page = 1): Promise<PaginatedResponse<Form>> {
    return this.request("GET", `/api/v1/forms?page=${page}`);
  }

  /** Get a single form by UUID. */
  async getForm(uuid: string): Promise<ApiResponse<Form>> {
    return this.request("GET", `/api/v1/forms/${uuid}`);
  }

  /** Get a single form by slug. */
  async getFormBySlug(slug: string): Promise<ApiResponse<Form>> {
    return this.request("GET", `/api/v1/forms/${slug}/by-slug`);
  }

  /** Get the full form schema (steps, fields, options, conditions, entities). */
  async getFormSchema(uuid: string): Promise<ApiResponse<FormSchema>> {
    return this.request("GET", `/api/v1/forms/${uuid}/schema`);
  }

  // ── Submissions (authenticated) ──────────────────────────────────────

  /** Create a new draft submission for a form. */
  async createSubmission(
    formUuid: string,
  ): Promise<ApiResponse<Submission>> {
    return this.request("POST", "/api/v1/submissions", {
      form_uuid: formUuid,
    });
  }

  /** Get a submission with its current values. */
  async getSubmission(
    uuid: string,
  ): Promise<ApiResponse<SubmissionDetail>> {
    return this.request("GET", `/api/v1/submissions/${uuid}`);
  }

  /** Update submission status and/or meta. */
  async updateSubmission(
    uuid: string,
    data: { status?: string; meta?: Record<string, unknown> },
  ): Promise<ApiResponse<Submission>> {
    return this.request("PATCH", `/api/v1/submissions/${uuid}`, data);
  }

  /** Upsert field values for a submission. */
  async storeValues(
    uuid: string,
    values: FieldValue[],
  ): Promise<ApiResponse<SubmissionDetail>> {
    return this.request("POST", `/api/v1/submissions/${uuid}/values`, {
      values,
    });
  }

  /** Advance the submission to the next step. */
  async advanceStep(uuid: string): Promise<StepResponse> {
    return this.request("POST", `/api/v1/submissions/${uuid}/advance`);
  }

  /** Retreat the submission to the previous step. */
  async retreatStep(uuid: string): Promise<StepResponse> {
    return this.request("POST", `/api/v1/submissions/${uuid}/retreat`);
  }

  /** Evaluate conditional field visibility/required states. */
  async getConditions(
    uuid: string,
  ): Promise<ApiResponse<FieldState[]>> {
    return this.request("GET", `/api/v1/submissions/${uuid}/conditions`);
  }

  // ── Internal ─────────────────────────────────────────────────────────

  private async request<T>(
    method: string,
    path: string,
    body?: unknown,
  ): Promise<T> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      "Content-Type": "application/json",
    };

    if (this.token) {
      headers.Authorization = `Bearer ${this.token}`;
    }

    const response = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
      const errorBody = await response.json().catch(() => null);
      throw new FlowForgeError(response.status, errorBody);
    }

    return response.json() as Promise<T>;
  }
}
