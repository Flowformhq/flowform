/**
 * Multi-Step Form with Conditional Fields
 *
 * Demonstrates the full workflow:
 * 1. Fetch a form schema
 * 2. Create a submission
 * 3. For each step:
 *    a. Evaluate conditions to determine visible/required fields
 *    b. Store values for the current step's fields
 *    c. Advance to the next step
 * 4. Mark the submission as completed
 *
 * Usage:
 *   npx tsx examples/multi-step-form.ts
 */

import {
  FlowFormClient,
  type FieldState,
  type FormSchema,
  type Step,
} from "../sdk/typescript/flowform";

const BASE_URL = process.env.FLOWFORM_URL ?? "http://localhost";
const TOKEN = process.env.FLOWFORM_TOKEN ?? "your-api-token";

// Simulated user input — in a real app this comes from form fields
const MOCK_ANSWERS: Record<string, string> = {
  full_name: "Alice Johnson",
  email: "alice@example.com",
  company: "Acme Inc.",
  role: "engineer",
  experience: "5",
  feedback: "Great product!",
};

async function main() {
  const client = new FlowFormClient(BASE_URL, TOKEN);

  // 1. Load the form schema
  const { data: schema } = await client.getFormBySlug("onboarding");
  const { data: fullSchema } = await client.getFormSchema(schema.uuid);
  console.log(`Form: ${fullSchema.name} (${fullSchema.steps.length} steps)\n`);

  // 2. Create a submission
  const { data: submission } = await client.createSubmission(fullSchema.uuid);
  console.log(`Submission created: ${submission.uuid}\n`);

  // 3. Walk through each step
  for (let i = 0; i < fullSchema.steps.length; i++) {
    const step = fullSchema.steps[i];
    console.log(`── Step ${step.step_number}: ${step.title} ──`);

    // 3a. Check which fields are visible and required
    const { data: conditions } = await client.getConditions(submission.uuid);
    const stateMap = new Map<string, FieldState>();
    for (const state of conditions) {
      stateMap.set(state.field_code, state);
    }

    // 3b. Collect values for visible fields in this step
    const values = collectStepValues(step, stateMap);
    if (values.length > 0) {
      await client.storeValues(submission.uuid, values);
      console.log(`  Stored ${values.length} value(s)`);
    }

    // 3c. Advance to the next step (skip on last step)
    if (i < fullSchema.steps.length - 1) {
      const { current_step } = await client.advanceStep(submission.uuid);
      console.log(`  Advanced to step ${current_step}\n`);
    }
  }

  // 4. Mark completed
  await client.updateSubmission(submission.uuid, { status: "completed" });
  console.log("\nSubmission completed!");

  // 5. Verify final state
  const { data: final } = await client.getSubmission(submission.uuid);
  console.log(`Status: ${final.status}`);
  console.log(`Progress: ${final.progress_percentage}%`);
  console.log("Final values:", final.values);
}

function collectStepValues(
  step: Step,
  stateMap: Map<string, FieldState>,
) {
  const values: { field_code: string; value: string | null }[] = [];

  for (const field of step.fields) {
    const state = stateMap.get(field.code);

    // Skip hidden fields
    if (state && !state.is_visible) {
      console.log(`  [hidden] ${field.code}`);
      continue;
    }

    const answer = MOCK_ANSWERS[field.code] ?? null;
    const required = state?.is_required ?? field.is_required;

    if (required && !answer) {
      console.warn(`  [missing] ${field.code} is required but has no value`);
    }

    if (answer !== null) {
      values.push({ field_code: field.code, value: answer });
      console.log(`  ${field.code} = "${answer}"${required ? " (required)" : ""}`);
    }
  }

  return values;
}

main().catch(console.error);
