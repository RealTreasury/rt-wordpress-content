<?php

class RTBCB_Router {
    public function handle_form_submission() {
        // Nonce verification
        if (!isset($_POST['rtbcb_nonce']) || !wp_verify_nonce($_POST['rtbcb_nonce'], 'rtbcb_form_action')) {
            wp_send_json_error(['message' => 'Nonce verification failed.'], 403);
            return;
        }

        try {
            // Sanitize and validate input
            $validator = new RTBCB_Validator();
            $validated_data = $validator->validate($_POST);

            if (isset($validated_data['error'])) {
                wp_send_json_error(['message' => $validated_data['error']], 400);
                return;
            }

            $form_data = $validated_data;

            // Instantiate necessary classes
            $calculator = new RTBCB_Calculator();
            $llm = new RTBCB_LLM();
            $rag = new RTBCB_RAG();

            // Perform calculations
            $calculations = $calculator->calculate($form_data);

            // Generate context from RAG
            $rag_context = $rag->get_context($form_data['company_description']);

            // Generate business case with LLM
            $business_case_data = $llm->generate_business_case($form_data, $calculations, $rag_context);

            // Save the lead
            $leads = new RTBCB_Leads();
            $lead_id = $leads->save_lead($form_data, $business_case_data);

            // Check for LLM generation errors
            if (is_wp_error($business_case_data)) {
                throw new Exception($business_case_data->get_error_message());
            }

            // Generate PDF
            $pdf = new RTBCB_PDF();
            $pdf_path = $pdf->generate($lead_id, $business_case_data);

            // Send success response
            wp_send_json_success([
                'message' => 'Business case generated successfully.',
                'report_id' => $lead_id,
                'pdf_url' => $pdf_path,
                'report_html' => $this->get_report_html($business_case_data),
            ]);

        } catch (Exception $e) {
            // Log the detailed error to debug.log
            error_log('RTBCB Form Submission Error: ' . $e->getMessage());

            // Send a generic error response to the client
            wp_send_json_error(['message' => 'An unexpected error occurred while generating your report. Please check the server logs for more details. Error: ' . $e->getMessage()], 500);
        }
    }
}

