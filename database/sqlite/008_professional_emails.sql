-- Professional transactional email copy. Existing rows are overwritten once; later
-- administrator edits are preserved because this migration runs only once.
UPDATE email_templates SET subject = 'Welcome to Wayfairs', body = 'Dear {first_name},

Welcome to Wayfairs. Your account has been created and is ready to use.

Sign in to complete identity verification before you deposit, withdraw, or transfer funds. Verification is required for all money movement.

If you did not create this account, contact support immediately.

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'registration';

UPDATE email_templates SET subject = 'Deposit request received - {amount} {currency}', body = 'Dear {first_name},

We have received your deposit request.

Amount: {amount} {currency}
Reference: {reference}
Status: Pending verification

The funds will be credited to your account once the deposit is confirmed. You will receive another notification when it is processed.

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'deposit';

UPDATE email_templates SET subject = 'Withdrawal request received - {amount} {currency}', body = 'Dear {first_name},

We have received your withdrawal request.

Amount: {amount} {currency}
Reference: {reference}
Status: Pending

The amount has been held from your available balance and will be released to your nominated account once processed. You will receive another notification when it is completed.

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'withdrawal';

UPDATE email_templates SET subject = 'Bank transfer submitted - {amount} {currency}', body = 'Dear {first_name},

Your bank transfer has been submitted.

Amount: {amount} {currency}
Beneficiary: {beneficiary}
Reference: {reference}
Status: Pending settlement

The amount has been held from your available balance. You will be notified when the transfer is completed or returned.

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'bank_transfer';

UPDATE email_templates SET subject = 'Bill payment submitted - {amount} {currency}', body = 'Dear {first_name},

Your bill payment has been submitted.

Amount: {amount} {currency}
Biller: {biller}
Reference: {reference}
Status: Pending settlement

The amount has been held from your available balance. You will be notified when the payment is completed or returned.

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'bill_payment';

UPDATE email_templates SET subject = 'Account credited - {amount} {currency}', body = 'Dear {first_name},

Your account has been credited.

Amount: {amount} {currency}
Reference: {reference}
Date: {date}

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'topup';

UPDATE email_templates SET subject = 'Account debited - {amount} {currency}', body = 'Dear {first_name},

Your account has been debited.

Amount: {amount} {currency}
Reference: {reference}
Date: {date}

If you did not authorize this transaction, contact support immediately.

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'debit';

UPDATE email_templates SET subject = 'Your Wayfairs verification code', body = 'Dear {first_name},

Your verification code is {code}. It expires in {minutes} minutes.

If you did not request this code, ignore this message and contact support if you are concerned about the security of your account.

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'otp';

UPDATE email_templates SET subject = 'Flight request received', body = 'Dear {first_name},

We have received your flight request.

Route: {origin} to {destination}
Status: Requested

Our team will contact you to confirm availability and pricing before any booking is made. No charge has been applied at this stage.

Regards,
Wayfairs', updated_at = datetime('now') WHERE template_key = 'flight_request';

INSERT INTO email_templates (id, template_key, subject, body, updated_at) VALUES
('account-status-default', 'account_status', 'Important update to your account', 'Dear {first_name},

There has been an update to your account.

Status: {status}

If you have questions about this change, contact support.

Regards,
Wayfairs', datetime('now'));
