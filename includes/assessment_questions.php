<?php
/**
 * includes/assessment_questions.php
 * Question banks per department. Each question:
 *   'q'      => question text
 *   'options'=> [4 options]
 *   'answer' => index (0-3) of correct option
 */

if (!function_exists('getAssessmentQuestions')) {
    function getAssessmentQuestions($department, $position = '') {
        $department = strtolower(trim($department ?? ''));
        $position   = strtolower(trim($position ?? ''));

        // ---------------- TRANSPORTATION / FLEET (Drivers etc.) ----------------
        if ($department === 'transportation' || $department === 'fleet') {
            if (strpos($position, 'driver') !== false && strpos($position, 'assistant') === false) {
                return [
                    ['q' => 'What does a red traffic light mean?', 'options' => ['Slow down', 'Stop completely', 'Speed up', 'Honk and proceed'], 'answer' => 1],
                    ['q' => 'What is the safe following distance for heavy trucks at highway speeds?', 'options' => ['1 second', '2 seconds', 'At least 4 seconds', 'Tailgating is fine'], 'answer' => 2],
                    ['q' => 'Before a long trip, which of these should you check?', 'options' => ['Radio station', 'Tire pressure, brakes, fluids', 'Only the fuel gauge', 'Nothing'], 'answer' => 1],
                    ['q' => 'What should you do if your brakes fail while driving?', 'options' => ['Jump out', 'Pump brakes, downshift, use emergency brake', 'Turn off engine immediately', 'Accelerate'], 'answer' => 1],
                    ['q' => 'The maximum legal blood alcohol content (BAC) for professional drivers is:', 'options' => ['0.08%', '0.05%', '0.00%', '0.10%'], 'answer' => 2],
                    ['q' => 'When should you use your hazard lights?', 'options' => ['While parking', 'When your vehicle is a hazard or stopped', 'Always', 'Never'], 'answer' => 1],
                    ['q' => 'What does a solid yellow line on your side of the road mean?', 'options' => ['You may pass anytime', 'Do not cross to pass', 'Speed up', 'Park here'], 'answer' => 1],
                    ['q' => 'How often should a commercial truck driver take a rest break?', 'options' => ['Every 8 hours', 'Every 2-3 hours of continuous driving', 'Once a day', 'Never'], 'answer' => 1],
                    ['q' => 'What is the correct action at a railway crossing with no barriers?', 'options' => ['Speed through', 'Stop, look, listen, then cross', 'Honk continuously', 'Close your eyes'], 'answer' => 1],
                    ['q' => 'Black ice is most likely to form:', 'options' => ['On a hot day', 'On bridges and shaded areas in cold weather', 'In the desert', 'Never'], 'answer' => 1],
                    ['q' => 'What is the proper way to handle a tire blowout?', 'options' => ['Slam the brakes', 'Grip wheel firmly, ease off gas, steer straight', 'Turn sharply', 'Accelerate'], 'answer' => 1],
                    ['q' => 'When reversing a truck, you should:', 'options' => ['Rely only on mirrors', 'Use a spotter and check surroundings', 'Reverse fast', 'Close windows'], 'answer' => 1],
                    ['q' => 'What does a load limit sign indicate?', 'options' => ['Vehicle speed limit', 'Maximum weight the road can handle', 'Height limit', 'Parking hours'], 'answer' => 1],
                    ['q' => 'If you feel sleepy while driving, you should:', 'options' => ['Drink coffee and continue', 'Pull over safely and rest', 'Open the window', 'Drive faster'], 'answer' => 1],
                    ['q' => 'The correct way to secure cargo is to:', 'options' => ['Use ropes loosely', 'Use approved straps and check tension', 'Leave it', 'Use tape'], 'answer' => 1],
                ];
            }
            // Fleet supervisors / officers / coordinators
            return [
                ['q' => 'What is the primary KPI for fleet operations?', 'options' => ['Fuel cost', 'On-time delivery rate', 'Number of vehicles', 'Driver age'], 'answer' => 1],
                ['q' => 'Preventive maintenance of fleet vehicles should be done:', 'options' => ['When they break down', 'On a scheduled basis per manufacturer specs', 'Once a year', 'Never'], 'answer' => 1],
                ['q' => 'How do you handle a driver who is consistently late?', 'options' => ['Fire immediately', 'Investigate, coach, and document', 'Ignore it', 'Give bonus'], 'answer' => 1],
                ['q' => 'Fleet utilization rate is calculated as:', 'options' => ['Total vehicles / drivers', 'Active hours ÷ available hours × 100', 'Fuel / distance', 'Mileage × speed'], 'answer' => 1],
                ['q' => 'The best way to reduce fleet fuel cost is:', 'options' => ['Buy cheaper fuel', 'Route optimization and driver training', 'Drive faster', 'Reduce vehicle checks'], 'answer' => 1],
                ['q' => 'GPS tracking in fleet management is used for:', 'options' => ['Entertainment', 'Real-time vehicle monitoring and route optimization', 'Music', 'None'], 'answer' => 1],
                ['q' => 'A vehicle inspection checklist should include:', 'options' => ['Only tires', 'Brakes, lights, fluids, tires, mirrors', 'Only fuel', 'Nothing'], 'answer' => 1],
                ['q' => 'If a vehicle is involved in an accident, the first step is:', 'options' => ['Leave the scene', 'Ensure safety and report immediately', 'Hide it', 'Blame the driver'], 'answer' => 1],
                ['q' => 'What is the correct way to handle vehicle registration renewal?', 'options' => ['Wait for expiration', 'Track and renew before expiry date', 'Skip it', 'Only if caught'], 'answer' => 1],
                ['q' => 'Driver fatigue is managed best by:', 'options' => ['Longer shifts', 'Scheduled rest and shift rotation', 'Coffee only', 'Ignoring it'], 'answer' => 1],
            ];
        }

        // ---------------- WAREHOUSE ----------------
        if ($department === 'warehouse') {
            return [
                ['q' => 'What does FIFO stand for in warehouse operations?', 'options' => ['First In First Out', 'Fast Inventory For Orders', 'Final In Final Out', 'Fixed Item For Output'], 'answer' => 0],
                ['q' => 'The safest way to lift a heavy box is:', 'options' => ['With your back bent', 'With knees bent, back straight', 'From the side', 'Quickly'], 'answer' => 1],
                ['q' => 'A forklift should only be operated by:', 'options' => ['Anyone available', 'Certified and trained operator', 'A supervisor', 'A visitor'], 'answer' => 1],
                ['q' => 'What is the purpose of a warehouse management system (WMS)?', 'options' => ['Entertainment', 'Track inventory, orders, and operations', 'Play music', 'None'], 'answer' => 1],
                ['q' => 'When stacking boxes, you should:', 'options' => ['Stack as high as possible', 'Stack according to weight and stability rules', 'Throw them', 'Randomly'], 'answer' => 1],
                ['q' => 'A safety data sheet (SDS) is used for:', 'options' => ['Tracking packages', 'Chemical hazard information', 'Customer info', 'None'], 'answer' => 1],
                ['q' => 'Inventory accuracy is critical because:', 'options' => ['It looks nice', 'It affects orders, cost, and customer satisfaction', 'It is optional', 'None'], 'answer' => 1],
                ['q' => 'The correct use of a pallet jack includes:', 'options' => ['Running with it', 'Pushing/pulling with control and awareness', 'Overloading', 'None'], 'answer' => 1],
                ['q' => 'Aisles in a warehouse should be:', 'options' => ['Cluttered', 'Clear and marked', 'Narrow', 'Locked'], 'answer' => 1],
                ['q' => 'Damaged goods should be:', 'options' => ['Shipped anyway', 'Segregated and reported', 'Hidden', 'Thrown away'], 'answer' => 1],
                ['q' => 'Personal Protective Equipment (PPE) in warehouse includes:', 'options' => ['Shorts and slippers', 'Helmet, gloves, safety shoes, vest', 'Sunglasses only', 'None'], 'answer' => 1],
                ['q' => 'A cycle count is:', 'options' => ['Full inventory count', 'Periodic partial inventory count', 'A type of bicycle', 'None'], 'answer' => 1],
            ];
        }

        // ---------------- CUSTOMER SERVICE ----------------
        if ($department === 'customer_service' || $department === 'customer service') {
            return [
                ['q' => 'The best way to handle an angry customer is:', 'options' => ['Argue back', 'Listen actively, empathize, then solve', 'Ignore them', 'Transfer'], 'answer' => 1],
                ['q' => 'What does "first call resolution" mean?', 'options' => ['First call of the day', 'Resolving the issue on first contact', 'Only calling once', 'None'], 'answer' => 1],
                ['q' => 'Customer service KPI examples include:', 'options' => ['Sales', 'Response time, CSAT, resolution rate', 'Coffee breaks', 'None'], 'answer' => 1],
                ['q' => 'When you don\'t know the answer to a customer question, you should:', 'options' => ['Make one up', 'Say you\'ll find out and follow up', 'Ignore them', 'Transfer'], 'answer' => 1],
                ['q' => 'Empathy in customer service means:', 'options' => ['Feeling sorry', 'Understanding and acknowledging the customer\'s feelings', 'Crying', 'None'], 'answer' => 1],
                ['q' => 'A customer is always right:', 'options' => ['Yes, always', 'No, but always treat them with respect', 'Never', 'Only on Mondays'], 'answer' => 1],
                ['q' => 'The correct way to put a customer on hold is:', 'options' => ['Silently', 'Ask permission and check back periodically', 'Leave on hold', 'Hang up'], 'answer' => 1],
                ['q' => 'CSAT stands for:', 'options' => ['Customer Satisfaction Score', 'Company Sales Activity Tracker', 'Client Support Assessment Tool', 'None'], 'answer' => 0],
                ['q' => 'A good customer service tone is:', 'options' => ['Robotic', 'Friendly, professional, and clear', 'Sarcastic', 'Loud'], 'answer' => 1],
                ['q' => 'When handling a complaint, the first step is:', 'options' => ['Blame the customer', 'Listen and acknowledge', 'Escalate', 'Refund'], 'answer' => 1],
            ];
        }

        // ---------------- SALES ----------------
        if ($department === 'sales' || $department === 'sales & business development') {
            return [
                ['q' => 'The first step in the sales process is:', 'options' => ['Closing', 'Prospecting', 'Objection handling', 'Follow-up'], 'answer' => 1],
                ['q' => 'A qualified lead means:', 'options' => ['Anyone', 'A contact that matches the ideal customer profile and has interest', 'A random email', 'None'], 'answer' => 1],
                ['q' => 'The best way to handle a price objection is:', 'options' => ['Lower price immediately', 'Highlight value and ROI', 'Ignore it', 'Walk away'], 'answer' => 1],
                ['q' => 'Sales pipeline stages typically include:', 'options' => ['Only closed', 'Lead, qualified, proposal, negotiation, closed', 'None', 'Coffee'], 'answer' => 1],
                ['q' => 'A CRM is used for:', 'options' => ['Music', 'Managing customer relationships and sales data', 'Gaming', 'None'], 'answer' => 1],
                ['q' => 'The best time to follow up after a meeting is:', 'options' => ['Never', 'Within 24-48 hours', 'One month later', 'Only if they call'], 'answer' => 1],
                ['q' => 'Cross-selling means:', 'options' => ['Selling to a competitor', 'Selling a complementary product to an existing customer', 'Refusing a sale', 'None'], 'answer' => 1],
                ['q' => 'Upselling means:', 'options' => ['Selling a cheaper product', 'Selling a premium version of the product', 'Selling to a stranger', 'None'], 'answer' => 1],
                ['q' => 'The best salespeople typically:', 'options' => ['Talk only', 'Listen more than they talk', 'Push hard', 'None'], 'answer' => 1],
                ['q' => 'Sales forecasting helps with:', 'options' => ['Entertainment', 'Planning, inventory, and resource allocation', 'None', 'Fun'], 'answer' => 1],
            ];
        }

        // ---------------- FINANCE / ACCOUNTING ----------------
        if ($department === 'finance' || $department === 'finance & accounting' || $department === 'accounting') {
            return [
                ['q' => 'The basic accounting equation is:', 'options' => ['Assets = Liabilities + Equity', 'Assets = Revenue', 'Cash = Profit', 'None'], 'answer' => 0],
                ['q' => 'A debit increases:', 'options' => ['Liabilities', 'Assets', 'Revenue', 'Equity'], 'answer' => 1],
                ['q' => 'A credit increases:', 'options' => ['Assets', 'Expenses', 'Liabilities', 'Drawings'], 'answer' => 2],
                ['q' => 'GAAP stands for:', 'options' => ['Generally Accepted Accounting Principles', 'General Accounting Audit Process', 'Government Approved Accounting Policy', 'None'], 'answer' => 0],
                ['q' => 'The income statement shows:', 'options' => ['Assets and liabilities', 'Revenue, expenses, and profit/loss over a period', 'Cash balance', 'Equity only'], 'answer' => 1],
                ['q' => 'The balance sheet shows:', 'options' => ['Revenue', 'Assets, liabilities, and equity at a point in time', 'Expenses', 'None'], 'answer' => 1],
                ['q' => 'Depreciation is:', 'options' => ['Increase in value', 'Allocation of asset cost over its useful life', 'A tax', 'None'], 'answer' => 1],
                ['q' => 'Working capital equals:', 'options' => ['Current assets − current liabilities', 'Total assets', 'Net income', 'None'], 'answer' => 0],
                ['q' => 'An audit is conducted to:', 'options' => ['Increase sales', 'Verify accuracy of financial records', 'Hire staff', 'None'], 'answer' => 1],
                ['q' => 'Petty cash is used for:', 'options' => ['Large payments', 'Small day-to-day expenses', 'Salaries', 'None'], 'answer' => 1],
                ['q' => 'Accounts receivable represent:', 'options' => ['Money owed to the company', 'Money the company owes', 'Cash on hand', 'None'], 'answer' => 0],
                ['q' => 'Accounts payable represent:', 'options' => ['Money owed to the company', 'Money the company owes to suppliers', 'Cash', 'None'], 'answer' => 1],
            ];
        }

        // ---------------- PROCUREMENT ----------------
        if ($department === 'procurement') {
            return [
                ['q' => 'A purchase order (PO) is used to:', 'options' => ['Advertise', 'Formally request goods/services from a supplier', 'Fire employees', 'None'], 'answer' => 1],
                ['q' => 'The best practice for supplier selection is:', 'options' => ['Lowest price only', 'Evaluate price, quality, delivery, and reliability', 'Random', 'Personal friend'], 'answer' => 1],
                ['q' => 'A request for quotation (RFQ) is:', 'options' => ['A customer complaint', 'A formal request for pricing from suppliers', 'A PO', 'None'], 'answer' => 1],
                ['q' => 'Three-way match involves:', 'options' => ['Three invoices', 'Purchase order, receiving report, and invoice', 'Three suppliers', 'None'], 'answer' => 1],
                ['q' => 'Lead time refers to:', 'options' => ['Price', 'Time between order and delivery', 'Quantity', 'None'], 'answer' => 1],
                ['q' => 'Ethical procurement means:', 'options' => ['Accepting gifts', 'Fairness, transparency, and no conflicts of interest', 'Favoritism', 'None'], 'answer' => 1],
                ['q' => 'A supplier scorecard is used for:', 'options' => ['Marketing', 'Evaluating supplier performance', 'Hiring', 'None'], 'answer' => 1],
                ['q' => 'Just-in-time (JIT) procurement aims to:', 'options' => ['Stockpile goods', 'Minimize inventory by ordering as needed', 'Delay orders', 'None'], 'answer' => 1],
                ['q' => 'A contract should include:', 'options' => ['Only price', 'Scope, price, terms, delivery, and penalties', 'Nothing', 'Verbal only'], 'answer' => 1],
                ['q' => 'Cost savings in procurement is achieved by:', 'options' => ['Buying low quality', 'Negotiation, bulk buying, and supplier management', 'Avoiding suppliers', 'None'], 'answer' => 1],
            ];
        }

        // ---------------- COMPLIANCE / LEGAL / RISK ----------------
        if ($department === 'compliance' || $department === 'compliance / legal & risk' || $department === 'legal') {
            return [
                ['q' => 'Compliance means:', 'options' => ['Following the law and company policies', 'Ignoring rules', 'Making laws', 'None'], 'answer' => 0],
                ['q' => 'A conflict of interest occurs when:', 'options' => ['Two employees agree', 'Personal interest interferes with professional duty', 'A meeting happens', 'None'], 'answer' => 1],
                ['q' => 'Data privacy laws protect:', 'options' => ['Company logos', 'Personal information of individuals', 'Buildings', 'None'], 'answer' => 1],
                ['q' => 'The correct way to handle a suspected fraud report is:', 'options' => ['Ignore it', 'Report through proper channels and investigate', 'Confront publicly', 'None'], 'answer' => 1],
                ['q' => 'A risk assessment involves:', 'options' => ['Identifying, analyzing, and mitigating risks', 'Ignoring risks', 'Only insurance', 'None'], 'answer' => 0],
                ['q' => 'Whistleblower protection is:', 'options' => ['Not important', 'Legal protection for those reporting misconduct', 'For criminals', 'None'], 'answer' => 1],
                ['q' => 'A company code of conduct is:', 'options' => ['Optional reading', 'A guide for ethical behavior expected of employees', 'Sales document', 'None'], 'answer' => 1],
                ['q' => 'Anti-money laundering (AML) laws aim to:', 'options' => ['Increase cash', 'Prevent criminals from disguising illegal funds', 'Lower taxes', 'None'], 'answer' => 1],
                ['q' => 'An NDA (non-disclosure agreement) is:', 'options' => ['A sales contract', 'A legal agreement to keep information confidential', 'An invoice', 'None'], 'answer' => 1],
                ['q' => 'Internal controls exist to:', 'options' => ['Slow business', 'Safeguard assets and ensure accuracy', 'Increase sales', 'None'], 'answer' => 1],
            ];
        }

        // ---------------- IT ----------------
        if ($department === 'it') {
            return [
                ['q' => 'What does CPU stand for?', 'options' => ['Central Processing Unit', 'Computer Personal Unit', 'Central Program Utility', 'None'], 'answer' => 0],
                ['q' => 'RAM stands for:', 'options' => ['Read Access Memory', 'Random Access Memory', 'Rapid Access Module', 'None'], 'answer' => 1],
                ['q' => 'Which is a valid IP address?', 'options' => ['192.168.1.1', '999.999.999.999', 'abc.def.ghi.jkl', 'None'], 'answer' => 0],
                ['q' => 'The best way to secure a password is:', 'options' => ['123456', 'Long, unique, with mix of chars', 'Your name', 'None'], 'answer' => 1],
                ['q' => 'SQL is used for:', 'options' => ['Design', 'Managing databases', 'Music', 'None'], 'answer' => 1],
                ['q' => 'A firewall is used for:', 'options' => ['Heating', 'Network security', 'Cooking', 'None'], 'answer' => 1],
                ['q' => 'What is cloud computing?', 'options' => ['Weather', 'Delivering computing services over the internet', 'A type of hardware', 'None'], 'answer' => 1],
                ['q' => 'A backup is important because:', 'options' => ['It looks nice', 'It protects data from loss', 'It slows systems', 'None'], 'answer' => 1],
                ['q' => 'The OSI model has how many layers?', 'options' => ['3', '5', '7', '9'], 'answer' => 2],
                ['q' => 'What does HTTP stand for?', 'options' => ['HyperText Transfer Protocol', 'High Tech Transfer Process', 'Hyper Terminal Transfer Protocol', 'None'], 'answer' => 0],
                ['q' => 'A VPN provides:', 'options' => ['Faster internet', 'Secure connection over public networks', 'Free WiFi', 'None'], 'answer' => 1],
                ['q' => 'Phishing is:', 'options' => ['A sport', 'A cyberattack tricking users to reveal info', 'A programming language', 'None'], 'answer' => 1],
            ];
        }

        // ---------------- SAFETY & SECURITY ----------------
        if ($department === 'safety_security' || $department === 'safety & security' || $department === 'safety' || $department === 'security') {
            return [
                ['q' => 'The first step in an emergency response is:', 'options' => ['Panic', 'Assess the situation and ensure safety', 'Run', 'None'], 'answer' => 1],
                ['q' => 'Fire extinguisher PASS stands for:', 'options' => ['Pull, Aim, Squeeze, Sweep', 'Push, Aim, Shoot, Stop', 'Pick, Assess, Spray, Stop', 'None'], 'answer' => 0],
                ['q' => 'A safety data sheet (SDS) provides info on:', 'options' => ['Chemicals and their hazards', 'Salaries', 'Customers', 'None'], 'answer' => 0],
                ['q' => 'PPE stands for:', 'options' => ['Personal Protective Equipment', 'Public Protection Enforcement', 'Private Property Entry', 'None'], 'answer' => 0],
                ['q' => 'An incident report should be filed:', 'options' => ['Never', 'Immediately after the incident', 'Next month', 'Only if severe'], 'answer' => 1],
                ['q' => 'The best way to prevent slips and falls is:', 'options' => ['Run', 'Keep floors clean, dry, and clear', 'Ignore spills', 'None'], 'answer' => 1],
                ['q' => 'Evacuation routes should be:', 'options' => ['Unknown', 'Clearly marked and unobstructed', 'Locked', 'None'], 'answer' => 1],
                ['q' => 'Lockout/Tagout procedures are used for:', 'options' => ['Cleaning', 'Isolating energy sources during maintenance', 'Lunch breaks', 'None'], 'answer' => 1],
                ['q' => 'A security guard\'s primary role is:', 'options' => ['Sleep', 'Protect people and property', 'Sales', 'None'], 'answer' => 1],
                ['q' => 'When handling an intruder, you should:', 'options' => ['Attack', 'Follow protocol, call for backup, do not confront alone', 'Ignore', 'None'], 'answer' => 1],
            ];
        }

        // ---------------- OPERATIONS ----------------
        if ($department === 'operations') {
            return [
                ['q' => 'Operations management focuses on:', 'options' => ['Marketing', 'Designing and controlling production processes', 'Sales', 'None'], 'answer' => 1],
                ['q' => 'A key operations KPI is:', 'options' => ['Employee birthday', 'Cycle time / throughput', 'Office decor', 'None'], 'answer' => 1],
                ['q' => 'Lean principles aim to:', 'options' => ['Increase waste', 'Eliminate waste and improve flow', 'Slow down', 'None'], 'answer' => 1],
                ['q' => 'Six Sigma is used for:', 'options' => ['Cooking', 'Reducing defects and improving quality', 'Hiring', 'None'], 'answer' => 1],
                ['q' => 'Capacity planning ensures:', 'options' => ['Overtime', 'Resources match demand', 'Higher price', 'None'], 'answer' => 1],
                ['q' => 'A bottleneck in operations is:', 'options' => ['A bottle', 'A constraint slowing down the process', 'A machine', 'None'], 'answer' => 1],
                ['q' => 'Standard Operating Procedures (SOPs) provide:', 'options' => ['Songs', 'Consistent steps to perform tasks', 'Prices', 'None'], 'answer' => 1],
                ['q' => 'Continuous improvement is also known as:', 'options' => ['Kaizen', 'Karaoke', 'Katana', 'None'], 'answer' => 0],
                ['q' => 'The purpose of a Gantt chart is:', 'options' => ['Music', 'Project scheduling and tracking', 'Design', 'None'], 'answer' => 1],
                ['q' => 'Operations should always prioritize:', 'options' => ['Speed only', 'Safety, quality, cost, and delivery', 'Fun', 'None'], 'answer' => 1],
            ];
        }

        // ---------------- DEFAULT (generic) ----------------
        return [
            ['q' => 'Time management means:', 'options' => ['Working late', 'Prioritizing and organizing tasks efficiently', 'Doing nothing', 'None'], 'answer' => 1],
            ['q' => 'Teamwork is important because:', 'options' => ['It looks nice', 'It combines strengths to achieve goals', 'It is mandatory', 'None'], 'answer' => 1],
            ['q' => 'The best way to handle a difficult task is:', 'options' => ['Ignore it', 'Break it into steps and start', 'Delegate always', 'None'], 'answer' => 1],
            ['q' => 'Professionalism includes:', 'options' => ['Loud behavior', 'Punctuality, respect, and accountability', 'Gossip', 'None'], 'answer' => 1],
            ['q' => 'When you make a mistake at work, you should:', 'options' => ['Hide it', 'Report it and learn from it', 'Blame others', 'None'], 'answer' => 1],
        ];
    }
}