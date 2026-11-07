UPDATE transactions SET description = 'Deposit' WHERE description = 'Deposit awaiting verification';
UPDATE transactions SET description = 'Withdrawal' WHERE description IN ('Withdrawal awaiting completion', 'Withdrawal pending');
UPDATE transactions SET description = 'Bank transfer' WHERE description = 'Bank transfer pending';
UPDATE transactions SET description = 'Bill payment' WHERE description = 'Bill payment pending';
