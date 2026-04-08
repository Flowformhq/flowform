/**
 * Simple Form Submission
 *
 * Demonstrates the minimal workflow:
 * 1. Fetch a form by slug
 * 2. Load its schema to discover fields
 * 3. Create a submission
 * 4. Store field values
 * 5. Mark the submission as completed
 *
 * Usage:
 *   npx tsx examples/simple-form.ts
 */

import { FlowFormClient } from "../sdk/typescript/flowform";

const BASE_URL = process.env.FLOWFORM_URL ?? "http://localhost";
const TOKEN = process.env.FLOWFORM_TOKEN ?? "your-api-token";

async function main() {
  const client = new FlowFormClient(BASE_URL, TOKEN);

  // 1. Find the form
  const { data: form } = await client.getFormBySlug("contact-us");
  console.log(`Form: ${form.name} (${form.uuid})`);

  // 2. Load the schema to see what fields are available
  const { data: schema } = await client.getFormSchema(form.uuid);
  for (const step of schema.steps) {
    console.log(`  Step ${step.step_number}: ${step.title}`);
    for (const field of step.fields) {
      console.log(`    - ${field.code} (${field.field_type.name})${field.is_required ? " *" : ""}`);
    }
  }

  // 3. Create a draft submission
  const { data: submission } = await client.createSubmission(form.uuid);
  console.log(`\nCreated submission: ${submission.uuid} (status: ${submission.status})`);

  // 4. Fill in field values
  const { data: updated } = await client.storeValues(submission.uuid, [
    { field_code: "name", value: "Alice Johnson" },
    { field_code: "email", value: "alice@example.com" },
    { field_code: "message", value: "Hello from FlowForm!" },
  ]);
  console.log("Values stored:", updated.values);

  // 5. Complete the submission
  const { data: completed } = await client.updateSubmission(submission.uuid, {
    status: "completed",
  });
  console.log(`\nSubmission ${completed.uuid} is now: ${completed.status}`);
}

main().catch(console.error);
