// Run with node tools/check_trade_route_frequency.js.
const fs = require('fs');
const assert = require('assert');
const vm = require('vm');
const template = fs.readFileSync(__dirname + '/../Templates/Build/17_route_form.tpl', 'utf8');
const source = template.match(/    function frequencySchedules\(start, interval\) \{[\s\S]*?\n    \}/)[0];
const context = {};
vm.createContext(context);
vm.runInContext(source, context);
const schedules = context.frequencySchedules;
assert.equal(schedules(0, 30).length, 48);
assert.equal(schedules(0, 60).length, 24);
assert.equal(schedules(0, 5).length, 288);
assert.equal(schedules(0, 1440).length, 1);
assert.equal(schedules(0, 1445).length, 0);
assert.equal(schedules(NaN, 30).length, 0);
// All supported minute slots, including non-divisors of a day and late starts.
for (let interval = 5; interval <= 1440; interval += 5) {
    for (const start of [0, 5, 480, 1425, 1435]) {
        const times = schedules(start, interval);
        assert.equal(times[0], start);
        assert(times.length <= 288);
        assert(times[times.length - 1] < 1440);
        for (let i = 1; i < times.length; i++) assert.equal(times[i] - times[i-1], interval);
        assert(1440 + times[0] - times[times.length - 1] >= interval);
    }
}
console.log('Trade route frequency checks passed');
