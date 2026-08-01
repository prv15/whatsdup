import type { Metadata } from "next";
import { LegalPage } from "../site-components";
export const metadata: Metadata = { title: "Terms of Service | WhatstheUp", description: "Terms governing use of the WhatstheUp platform." };
export default function Terms() { return <LegalPage eyebrow="Legal" title="Terms of Service" intro="These terms govern access to and use of the WhatstheUp business messaging platform.">
  <h2>1. Eligibility and accounts</h2><p>You must be authorised to act for the business using WhatstheUp, provide accurate information and protect account credentials. You are responsible for activity by users you invite.</p>
  <h2>2. Acceptable use</h2><p>You may use WhatstheUp only for lawful business communication through official platform capabilities. You must not send unsolicited messages, upload contacts without a valid basis, evade opt-outs, misrepresent your identity, attempt unauthorised access or use the service for prohibited goods, abuse, fraud or unlawful content.</p>
  <h2>3. WhatsApp Business Platform obligations</h2><p>You are responsible for complying with Meta and WhatsApp terms, commerce and messaging policies, template rules, messaging windows, consent requirements and applicable laws. Meta may review, reject, pause or disable templates, numbers or accounts.</p>
  <h2>4. Customer data</h2><p>You retain responsibility for your contacts, consent evidence, campaign content and instructions. You grant WhatstheUp permission to process this data solely to provide, secure and support the service.</p>
  <h2>5. Availability and external services</h2><p>Some functions depend on Meta, hosting, telecommunications and other external services. We do not guarantee external approvals, delivery to every recipient or uninterrupted availability.</p>
  <h2>6. Suspension and termination</h2><p>We may restrict access when needed to address security, non-payment, legal risk, policy violations or platform restrictions. You may request closure and deletion subject to retention obligations.</p>
  <h2>7. Warranties and liability</h2><p>The service is provided with reasonable care but without guarantees beyond those required by law. To the extent permitted by law, WhatstheUp is not liable for indirect losses, lost profits, customer decisions, Meta enforcement or failures outside our reasonable control.</p>
  <h2>8. Contact</h2><p>Questions about these terms can be sent to <a href="mailto:legal@whatstheup.in">legal@whatstheup.in</a>. These terms should be reviewed with qualified counsel before commercial launch.</p>
</LegalPage>; }
