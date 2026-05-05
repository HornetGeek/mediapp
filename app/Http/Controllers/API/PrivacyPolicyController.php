<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

class PrivacyPolicyController extends Controller
{
    public function __invoke()
    {
        return ApiResponse::sendResponse(200, 'Privacy Policy Found', [
            'title' => 'Privacy Policy for Medical Visits Scheduling Application (Egypt-Compliant)',
            'effective_date' => '[Insert Date]',
            'content' => <<<'POLICY'
Privacy Policy for Medical Visits Scheduling Application (Egypt-Compliant)
Effective Date: [Insert Date]
1. Introduction
This Privacy Policy explains how [Company Name] ("we", "our", or "the Company") collects, uses, processes, and protects personal data through the medical visits scheduling application (the "App").
This policy is designed in accordance with Egypt Personal Data Protection Law No. 151 of 2020.
By registering or using the App, you acknowledge that you have read and explicitly consent to this Privacy Policy.
2. Data Controller Information
Company Name: [Insert Legal Company Name]
Address: [Insert Address in Egypt]
Email: [Insert Email]
Phone: [Insert Phone Number]
The Company acts as the Data Controller for all personal data processed through the App.
3. Legal Basis for Processing
We process personal data based on:
Your explicit consent
The necessity to perform our contractual obligations (providing appointment scheduling services)
Compliance with legal obligations under Egyptian law
4. Information We Collect
4.1 Personal Data
Full name
Phone number
Email address
Job title (doctor / medical representative)
Company or clinic affiliation
4.2 Professional Data (Doctors)
Specialty
Clinic/hospital details
Availability schedules
Appointment history
4.3 Technical Data
Device information
IP address
Usage data and logs
5. Purpose of Data Processing
We use personal data to:
Facilitate and manage appointment bookings
Organize medical representative visits efficiently
Send confirmations, reminders, and notifications
Improve application performance and user experience
Provide customer support
Prevent fraud or misuse
6. Data Sharing and Disclosure
We do not sell personal data.
We may share data only in the following cases:
Between doctors and medical representatives strictly for scheduling purposes
With third-party service providers (e.g., hosting, cloud storage, SMS/OTP providers) under confidentiality obligations
If required by Egyptian law, court order, or governmental authority
To protect legal rights or prevent fraud
7. Cross-Border Data Transfers
Your data may be stored or processed on servers located outside the Arab Republic of Egypt.
In such cases, we ensure that:
Adequate data protection measures are implemented
Transfers comply with applicable legal requirements
8. Data Retention
We retain personal data only for as long as necessary to:
Provide the service
Fulfill legal obligations
Resolve disputes
Data is securely deleted or anonymized when no longer required.
9. Data Security
We implement appropriate technical and organizational measures, including:
Encryption of data during transmission
Secure servers and restricted access
Role-based access control
Regular system monitoring and updates
10. Confidentiality of Doctors' Data
We are committed to maintaining the strict confidentiality of doctors' professional data.
Such data:
Is accessible only to authorized users
Is used solely for professional scheduling purposes
Will not be disclosed for marketing or unrelated activities without consent
11. User Rights Under Egyptian Law
In accordance with applicable laws, you have the right to:
Access your personal data
Request correction or updating of inaccurate data
Request deletion of your data
Withdraw your consent at any time
Object to or restrict certain processing activities
To exercise your rights, contact us at: [Insert Email]
12. Data Breach Notification
In the event of a data breach that may affect your personal data, we will:
Notify competent authorities as required by law
Inform affected users when necessary
13. Third-Party Services
The App may use third-party providers such as:
Cloud hosting services
Analytics tools
SMS/OTP verification providers
These providers are contractually obligated to protect your data.
14. Data Protection Officer (If Applicable)
Where required by law, the Company will appoint a Data Protection Officer (DPO).
For inquiries related to data protection, contact:
[Insert DPO Email or Contact]
15. Cookies and Tracking Technologies
We may use cookies or similar technologies to:
Improve functionality
Analyze usage
Enhance user experience
16. Updates to This Policy
We may update this Privacy Policy periodically.
Users will be notified of significant changes via the App or email.
17. Contact Us
For any privacy-related inquiries:
Email: [Insert Email]
Phone: [Insert Phone]
18. Governing Law
This Privacy Policy shall be governed by and interpreted in accordance with the laws of the Arab Republic of Egypt.
By using the App, you confirm your explicit consent to this Privacy Policy and the processing of your personal data as described above
POLICY,
        ]);
    }
}
