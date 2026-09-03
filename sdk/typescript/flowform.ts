// FlowForm TypeScript SDK
// Zero dependencies — uses native fetch.

export interface PaginatedResponse<T> {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
  };
}

export interface ApiResponse<T> {
  data: T;
}

export interface Form {
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean | number;
  version: number;
  created_at: string;
}

export interface FormSchema {
  uuid: string;
  name: string;
  slug: string;
  description: string | null;
  steps: Step[];
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

export interface Field {
  id: number;
  code: string;
  label: string;
  placeholder: string | null;
  description: string | null;
  is_required: boolean;
  is_repeatable: boolean;
  order: number;
  config?: Record<string, unknown> | null;
  field_type: FieldType;
  options: FieldOption[];
  conditions?: Condition[];
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
  source_field_code: string;
  operator: string;
  target_value: string | null;
  action: string;
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

export interface FieldValue {
  field_code: string;
  value: string | null;
}

export interface StepResponse {
  current_step: number;
}

export interface FieldState {
  field_id: number;
  field_code: string;
  is_visible: boolean;
  is_required: boolean;
}

export class FlowFormError extends Error {
  constructor(
    public status: number,
    public body: unknown,
  ) {
    super(`FlowForm API error [${status}]: ${JSON.stringify(body)}`);
    this.name = "FlowFormError";
  }
}

export class FlowFormClient {
  constructor(
    private baseUrl: string,
    private token?: string,
  ) {
    this.baseUrl = baseUrl.replace(/\/+$/, "");
  }

  // ── Public endpoints ──────────────────────────────────────────────────

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

  /** Get the complete form schema (steps, fields, options, conditions). */
  async getFormSchema(uuid: string): Promise<ApiResponse<FormSchema>> {
    return this.request("GET", `/api/v1/forms/${uuid}/schema`);
  }

  // ── Submission endpoints (Smart Routing: Token or Public) ─────────────

  /** Create a new draft submission. Uses public endpoint if no token is configured. */
  async createSubmission(formUuid: string): Promise<ApiResponse<Submission>> {
    const endpoint = this.token ? "/api/v1/submissions" : "/api/v1/public/submissions";
    return this.request("POST", endpoint, { form_uuid: formUuid });
  }

  /** Get submission details by UUID. */
  async getSubmission(uuid: string): Promise<ApiResponse<SubmissionDetail>> {
    const endpoint = this.token ? `/api/v1/submissions/${uuid}` : `/api/v1/public/submissions/${uuid}`;
    return this.request("GET", endpoint);
  }

  /** Update submission status or meta. */
  async updateSubmission(
    uuid: string,
    data: {
      status?: "draft" | "completed" | "abandoned";
      meta?: Record<string, unknown>;
    },
  ): Promise<ApiResponse<Submission>> {
    const endpoint = this.token ? `/api/v1/submissions/${uuid}` : `/api/v1/public/submissions/${uuid}`;
    return this.request("PATCH", endpoint, data);
  }

  /** Upsert field values for a submission. */
  async storeValues(
    uuid: string,
    values: FieldValue[],
  ): Promise<ApiResponse<SubmissionDetail>> {
    const endpoint = this.token ? `/api/v1/submissions/${uuid}/values` : `/api/v1/public/submissions/${uuid}/values`;
    return this.request("POST", endpoint, {
      values,
    });
  }

  /** Advance the submission to the next step. */
  async advanceStep(uuid: string): Promise<StepResponse> {
    const endpoint = this.token ? `/api/v1/submissions/${uuid}/advance` : `/api/v1/public/submissions/${uuid}/advance`;
    return this.request("POST", endpoint);
  }

  /** Retreat the submission to the previous step. */
  async retreatStep(uuid: string): Promise<StepResponse> {
    const endpoint = this.token ? `/api/v1/submissions/${uuid}/retreat` : `/api/v1/public/submissions/${uuid}/retreat`;
    return this.request("POST", endpoint);
  }

  /** Evaluate conditional field visibility/required states. */
  async getConditions(
    uuid: string,
  ): Promise<ApiResponse<FieldState[]>> {
    const endpoint = this.token ? `/api/v1/submissions/${uuid}/conditions` : `/api/v1/public/submissions/${uuid}/conditions`;
    return this.request("GET", endpoint);
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
      throw new FlowFormError(response.status, errorBody);
    }

    return response.json() as Promise<T>;
  }
}
