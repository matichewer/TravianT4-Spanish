## 1. Ranking score consistency

- [x] 1.1 Add database helpers that atomically update player/alliance weekly scores and reconcile alliance caches
- [x] 1.2 Route combat attack, defense, and net-raiding updates through the consistent helper

## 2. Alliance membership consistency

- [x] 2.1 Add a membership-change operation that transfers weekly contributions and rebases population tracking
- [x] 2.2 Route alliance creation, joining, leaving, and expulsion through the membership operation

## 3. Top 10 presentation

- [x] 3.1 Suppress the redundant current-player row when it is already in each player Top 10
- [x] 3.2 Suppress the redundant current-alliance row when it is already in each alliance Top 10

## 4. Verification

- [x] 4.1 Add regression coverage for score attribution, reconciliation, membership transfer, and unique Top 10 rows
- [x] 4.2 Run strict OpenSpec validation, PHP syntax checks, every regression checker, and ranking-page smoke tests
