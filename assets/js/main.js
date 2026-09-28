document.addEventListener("DOMContentLoaded", function () {
    const savedTheme = localStorage.getItem("campusCoinTheme");

    if (savedTheme === "dark") {
        document.body.classList.add("dark-theme");
    }

    const mobileMenuButton = document.getElementById("mobileMenuBtn");
    const studentSidebar = document.getElementById("studentSidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    function closeMobileSidebar() {
        if (studentSidebar) {
            studentSidebar.classList.remove("mobile-open");
        }

        if (sidebarOverlay) {
            sidebarOverlay.classList.remove("show");
        }

        if (mobileMenuButton) {
            mobileMenuButton.setAttribute("aria-expanded", "false");
            mobileMenuButton.setAttribute("aria-label", "Open navigation");
            mobileMenuButton.textContent = "☰";
        }
    }

    if (mobileMenuButton && studentSidebar) {
        mobileMenuButton.addEventListener("click", function () {
            const isOpen = studentSidebar.classList.toggle("mobile-open");

            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle("show", isOpen);
            }

            mobileMenuButton.setAttribute("aria-expanded", String(isOpen));
            mobileMenuButton.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");
            mobileMenuButton.textContent = isOpen ? "×" : "☰";
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener("click", closeMobileSidebar);
    }

    document.querySelectorAll(".sidebar .nav-link, .sidebar .logout-link").forEach(function (link) {
        link.addEventListener("click", closeMobileSidebar);
    });

    window.addEventListener("resize", function () {
        if (window.innerWidth > 760) {
            closeMobileSidebar();
        }
    });

    const themeButton = document.getElementById("themeToggle");

    if (themeButton) {
        themeButton.addEventListener("click", function () {
            document.body.classList.toggle("dark-theme");

            const currentTheme = document.body.classList.contains("dark-theme")
                ? "dark"
                : "light";

            localStorage.setItem("campusCoinTheme", currentTheme);
        });
    }

    const transactionForm = document.getElementById("transactionForm");

    if (transactionForm) {
        if (!transactionForm.getAttribute("action") || transactionForm.getAttribute("action") === "#") {
            transactionForm.addEventListener("submit", function (event) {
                event.preventDefault();
                saveTransaction();
            });
        }
    }

    const transactionDate = document.getElementById("transactionDate");

    if (transactionDate && !transactionDate.value) {
        transactionDate.value = getTodayDate();
    }

    const categoryForm = document.getElementById("categoryForm");

    if (categoryForm) {
        if (!categoryForm.getAttribute("action") || categoryForm.getAttribute("action") === "#") {
            categoryForm.addEventListener("submit", function (event) {
                event.preventDefault();
                saveCategory();
            });
        }
    }

    const budgetForm = document.getElementById("budgetForm");

    if (budgetForm) {
        if (!budgetForm.getAttribute("action") || budgetForm.getAttribute("action") === "#") {
            budgetForm.addEventListener("submit", function (event) {
                event.preventDefault();
                saveBudget();
            });
        }
    }

    initializeMonthlyCycle();
    updateCurrentDateTime();
    setInterval(updateCurrentDateTime, 60000);
    initializeDashboard();
    initializeSavingTip();
    initializeCategoriesPage();
    initializeBudgetsPage();
    initializeReportsPage();
});

function getCurrentMonthKey() {
    const date = new Date();
    return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0");
}

function getCurrentMonthName() {
    return new Date().toLocaleDateString("en-US", { month: "long", year: "numeric" });
}

function getCurrentTime() {
    const date = new Date();
    return String(date.getHours()).padStart(2, "0") + ":" + String(date.getMinutes()).padStart(2, "0");
}

function getMonthKeyFromDate(dateString) {
    if (!dateString) {
        return getCurrentMonthKey();
    }

    const parts = String(dateString).split("-");

    if (parts.length >= 2) {
        return parts[0] + "-" + parts[1];
    }

    return getCurrentMonthKey();
}

function formatTime(timeString) {
    if (!timeString) {
        return "";
    }

    const parts = String(timeString).split(":");
    const hours = Number(parts[0]);
    const minutes = String(parts[1] || "00").padStart(2, "0");

    if (Number.isNaN(hours)) {
        return timeString;
    }

    const period = hours >= 12 ? "PM" : "AM";
    const displayHour = hours % 12 || 12;
    return displayHour + ":" + minutes + " " + period;
}

function getDemoTransactions() {
    return [
        { id: 1, type: "expense", title: "Campus cafe", amount: 4.50, category: "Food", date: getTodayDate(), time: "10:30" },
        { id: 2, type: "expense", title: "Bus card top-up", amount: 8.00, category: "Transport", date: getYesterdayDate(), time: "09:15" },
        { id: 3, type: "income", title: "Monthly allowance", amount: 300.00, category: "Allowance", date: getDaysAgoDate(2), time: "08:00" },
        { id: 4, type: "expense", title: "Hostel mess bill", amount: 45.00, category: "Hostel/Rent", date: getDaysAgoDate(3), time: "18:30" }
    ];
}

function getAllTransactions() {
    const currentKey = getCurrentMonthKey();
    const currentStorage = "campusCoinAllTransactions";
    const historyStorage = "campusCoinTransactionHistory";
    const oldStorage = "campusCoinTransactions";
    let allTransactions = [];

    const savedCurrent = localStorage.getItem(currentStorage);
    const savedHistory = localStorage.getItem(historyStorage);

    if (savedCurrent) {
        try {
            allTransactions = allTransactions.concat(JSON.parse(savedCurrent));
        } catch (error) {
            allTransactions = [];
        }
    }

    if (savedHistory) {
        try {
            allTransactions = allTransactions.concat(JSON.parse(savedHistory));
        } catch (error) {
            // Keep valid current data.
        }
    }

    if (allTransactions.length === 0) {
        const oldData = localStorage.getItem(oldStorage);

        if (oldData) {
            try {
                allTransactions = JSON.parse(oldData);
            } catch (error) {
                allTransactions = [];
            }
        }
    }

    const hadStoredTransactions = allTransactions.length > 0;

    if (!hadStoredTransactions) {
        allTransactions = getDemoTransactions();
    }

    const unique = {};

    allTransactions.forEach(function (transaction) {
        if (!transaction || transaction.id === undefined) {
            return;
        }

        if (!transaction.date) {
            transaction.date = getTodayDate();
        }

        if (!transaction.time) {
            transaction.time = "12:00";
        }

        transaction.monthKey = getMonthKeyFromDate(transaction.date);
        unique[String(transaction.id)] = transaction;
    });

    const normalized = Object.values(unique);
    const currentTransactions = normalized.filter(function (transaction) {
        return transaction.monthKey === currentKey;
    });
    const historyTransactions = normalized.filter(function (transaction) {
        return transaction.monthKey !== currentKey;
    });

    localStorage.setItem(currentStorage, JSON.stringify(currentTransactions));
    localStorage.setItem(historyStorage, JSON.stringify(historyTransactions));
    localStorage.removeItem(oldStorage);

    return currentTransactions.concat(historyTransactions);
}

function getTransactions() {
    const currentKey = getCurrentMonthKey();
    const allTransactions = getAllTransactions();
    const currentTransactions = allTransactions.filter(function (transaction) {
        return transaction.monthKey === currentKey;
    });

    return currentTransactions;
}

function saveTransactions(transactions) {
    const currentKey = getCurrentMonthKey();
    const allTransactions = getAllTransactions();
    const historyTransactions = allTransactions.filter(function (transaction) {
        return transaction.monthKey !== currentKey;
    });

    const normalized = transactions.map(function (transaction) {
        if (!transaction.date) {
            transaction.date = getTodayDate();
        }

        if (!transaction.time) {
            transaction.time = getCurrentTime();
        }

        transaction.monthKey = getMonthKeyFromDate(transaction.date);
        return transaction;
    });

    const currentTransactions = normalized.filter(function (transaction) {
        return transaction.monthKey === currentKey;
    });
    const movedTransactions = normalized.filter(function (transaction) {
        return transaction.monthKey !== currentKey;
    });

    localStorage.setItem("campusCoinAllTransactions", JSON.stringify(currentTransactions));
    localStorage.setItem("campusCoinTransactionHistory", JSON.stringify(historyTransactions.concat(movedTransactions)));
    localStorage.removeItem("campusCoinTransactions");
}

function openTransactionModal(type) {
    const modal = document.getElementById("transactionModal");
    const typeInput = document.getElementById("transactionType");
    const title = document.getElementById("transactionModalTitle");
    const eyebrow = document.getElementById("transactionModalEyebrow");
    const form = document.getElementById("transactionForm");
    const titleInput = document.getElementById("transactionTitle");
    const amountInput = document.getElementById("transactionAmount");

    if (!modal || !typeInput || !title || !eyebrow) {
        return;
    }

    if (form) {
        form.reset();
    }

    typeInput.value = type;

    if (type === "income") {
        eyebrow.textContent = "Money coming in";
        title.textContent = "Add income";

        if (titleInput) {
            titleInput.placeholder = "e.g. Monthly allowance";
        }
    } else {
        eyebrow.textContent = "Money going out";
        title.textContent = "Add expense";

        if (titleInput) {
            titleInput.placeholder = "e.g. Lunch at campus cafe";
        }
    }

    updateTransactionCategories(type);

    const dateInput = document.getElementById("transactionDate");

    if (dateInput) {
        dateInput.value = getTodayDate();
    }

    if (amountInput) {
        amountInput.value = "";
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function getBuiltInCategories(type) {
    if (type === "income") {
        return [
            "Allowance",
            "Salary / Part-time",
            "Scholarship",
            "Gift",
            "Other income"
        ];
    }

    return [
        "Food",
        "Transport",
        "Academics",
        "Hostel/Rent",
        "Subscriptions",
        "Shopping",
        "Other expense"
    ];
}

function getCustomCategories() {
    const savedCategories = localStorage.getItem("campusCoinCategories");

    if (!savedCategories) {
        return [];
    }

    try {
        const categories = JSON.parse(savedCategories);
        return Array.isArray(categories) ? categories : [];
    } catch (error) {
        return [];
    }
}

function saveCustomCategories(categories) {
    localStorage.setItem(
        "campusCoinCategories",
        JSON.stringify(categories)
    );
}

function updateTransactionCategories(type) {
    const categorySelect = document.getElementById("transactionCategory");

    if (!categorySelect) {
        return;
    }

    categorySelect.innerHTML = "";

    const categories = getBuiltInCategories(type);
    const customCategories = getCustomCategories();

    customCategories.forEach(function (category) {
        if (category.type === type && !categories.includes(category.name)) {
            categories.push(category.name);
        }
    });

    categories.forEach(function (category) {
        const option = document.createElement("option");
        option.value = category;
        option.textContent = category;
        categorySelect.appendChild(option);
    });
}

function closeTransactionModal() {
    const modal = document.getElementById("transactionModal");

    if (!modal) {
        return;
    }

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
}

function saveTransaction() {
    const type = document.getElementById("transactionType").value;
    const title = document.getElementById("transactionTitle").value.trim();
    const amount = Number(document.getElementById("transactionAmount").value);
    const category = document.getElementById("transactionCategory").value;
    const date = document.getElementById("transactionDate").value;

    if (!title || !amount || amount <= 0 || !category || !date) {
        showDemoMessage("Please fill all transaction fields.");
        return;
    }

    const transactions = getTransactions();

    transactions.unshift({
        id: Date.now(),
        type: type,
        title: title,
        amount: amount,
        category: category,
        date: date
    });

    saveTransactions(transactions);
    closeTransactionModal();
    initializeDashboard();

    if (type === "income") {
        showDemoMessage("Income added successfully.");
    } else {
        showDemoMessage("Expense added successfully.");
    }
}

function deleteTransaction(id) {
    const transactions = getTransactions();
    const updatedTransactions = transactions.filter(function (transaction) {
        return transaction.id !== id;
    });

    saveTransactions(updatedTransactions);
    initializeDashboard();
    showDemoMessage("Transaction removed.");
}

function initializeSavingTip() {
    const tipCard = document.getElementById("dashboardSavingTip");
    const pinButton = document.getElementById("pinTipButton");
    const pinnedBadge = document.getElementById("tipPinnedBadge");

    if (!tipCard) {
        return;
    }

    const state = localStorage.getItem("campusCoinSavingTipState") || "visible";

    if (state === "dismissed") {
        tipCard.hidden = true;
        return;
    }

    tipCard.hidden = false;

    if (state === "pinned") {
        if (pinnedBadge) {
            pinnedBadge.hidden = false;
        }

        if (pinButton) {
            pinButton.textContent = "✓ Pinned";
            pinButton.classList.add("is-pinned");
        }
    } else {
        if (pinnedBadge) {
            pinnedBadge.hidden = true;
        }

        if (pinButton) {
            pinButton.textContent = "Pin this tip";
            pinButton.classList.remove("is-pinned");
        }
    }
}

function pinSavingTip() {
    localStorage.setItem("campusCoinSavingTipState", "pinned");
    initializeSavingTip();
    showDemoMessage("Saving tip pinned successfully.");
}

function dismissSavingTip() {
    localStorage.setItem("campusCoinSavingTipState", "dismissed");
    initializeSavingTip();
    showDemoMessage("Saving tip dismissed.");
}

function initializeDashboard() {
    const transactionList = document.getElementById("transactionList");

    if (!transactionList) {
        return;
    }

    const transactions = getTransactions();
    let income = 0;
    let expense = 0;

    transactions.forEach(function (transaction) {
        if (transaction.type === "income") {
            income += Number(transaction.amount);
        } else {
            expense += Number(transaction.amount);
        }
    });

    const balance = income - expense;

    updateMoneyElement("incomeAmount", income);
    updateMoneyElement("expenseAmount", expense);
    updateMoneyElement("balanceAmount", balance);

    renderTransactions(transactions);
    updateCategorySummary(transactions);
    updateChart(income, expense);
}

function updateMoneyElement(elementId, amount) {
    const element = document.getElementById(elementId);

    if (!element) {
        return;
    }

    element.textContent = "Rs. " + amount.toFixed(2);
}

function renderTransactions(transactions) {
    const list = document.getElementById("transactionList");

    if (!list) {
        return;
    }

    if (transactions.length === 0) {
        list.innerHTML = '<div class="transaction-empty">No transactions yet. Add your first income or expense.</div>';
        return;
    }

    list.innerHTML = "";

    transactions.slice(0, 6).forEach(function (transaction) {
        const row = document.createElement("div");
        row.className = "transaction";

        const icon = document.createElement("div");
        icon.className = "transaction-icon " + getCategoryClass(transaction.category);
        icon.textContent = getCategoryIcon(transaction.category);

        const info = document.createElement("div");
        info.className = "transaction-info";

        const name = document.createElement("strong");
        name.textContent = transaction.title;

        const details = document.createElement("span");
        details.textContent = transaction.category + " · " + formatDate(transaction.date) + " · " + formatTime(transaction.time);

        info.appendChild(name);
        info.appendChild(details);

        const amount = document.createElement("b");
        amount.className = transaction.type === "income" ? "income" : "expense";
        amount.textContent = transaction.type === "income"
            ? "+Rs. " + Number(transaction.amount).toFixed(2)
            : "-Rs. " + Number(transaction.amount).toFixed(2);

        const deleteButton = document.createElement("button");
        deleteButton.className = "transaction-delete";
        deleteButton.type = "button";
        deleteButton.textContent = "Delete";
        deleteButton.addEventListener("click", function () {
            deleteTransaction(transaction.id);
        });

        row.appendChild(icon);
        row.appendChild(info);
        row.appendChild(amount);
        row.appendChild(deleteButton);

        list.appendChild(row);
    });
}

function updateCategorySummary(transactions) {
    const categoryTotals = {};

    transactions.forEach(function (transaction) {
        if (transaction.type !== "expense") {
            return;
        }

        if (!categoryTotals[transaction.category]) {
            categoryTotals[transaction.category] = 0;
        }

        categoryTotals[transaction.category] += Number(transaction.amount);
    });

    const entries = Object.entries(categoryTotals).sort(function (a, b) {
        return b[1] - a[1];
    });

    const topCategory = document.querySelector(".category-highlight h3");
    const topCategoryText = document.querySelector(".category-highlight p");

    if (topCategory && topCategoryText && entries.length > 0) {
        topCategory.textContent = entries[0][0];
        topCategoryText.textContent = "Rs. " + entries[0][1].toFixed(2) + " spent";
    }
}

function updateChart(income, expense) {
    const chartElement = document.getElementById("incomeExpenseChart");

    if (!chartElement || typeof Chart === "undefined") {
        return;
    }

    if (window.campusCoinChart) {
        window.campusCoinChart.destroy();
    }

    window.campusCoinChart = new Chart(chartElement, {
        type: "line",
        data: {
            labels: ["Current month"],
            datasets: [
                {
                    label: "Income",
                    data: [income],
                    borderWidth: 2,
                    tension: 0.4
                },
                {
                    label: "Expense",
                    data: [expense],
                    borderWidth: 2,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: "bottom"
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function openCategoryModal() {
    const modal = document.getElementById("categoryModal");
    const form = document.getElementById("categoryForm");
    const nameInput = document.getElementById("categoryName");

    if (!modal) {
        return;
    }

    if (form) {
        form.reset();
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");

    if (nameInput) {
        setTimeout(function () {
            nameInput.focus();
        }, 100);
    }
}

function closeCategoryModal() {
    const modal = document.getElementById("categoryModal");

    if (!modal) {
        return;
    }

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
}

function saveCategory() {
    const nameInput = document.getElementById("categoryName");
    const typeInput = document.getElementById("categoryType");

    if (!nameInput || !typeInput) {
        return;
    }

    const name = nameInput.value.trim();
    const type = typeInput.value;

    if (!name) {
        showDemoMessage("Please enter a category name.");
        return;
    }

    const allNames = [];

    getBuiltInCategories(type).forEach(function (category) {
        allNames.push(category.toLowerCase());
    });

    const customCategories = getCustomCategories();

    customCategories.forEach(function (category) {
        if (category.type === type) {
            allNames.push(category.name.toLowerCase());
        }
    });

    if (allNames.includes(name.toLowerCase())) {
        showDemoMessage("This category already exists.");
        return;
    }

    customCategories.push({
        id: Date.now(),
        name: name,
        type: type
    });

    saveCustomCategories(customCategories);
    closeCategoryModal();
    initializeCategoriesPage();
    showDemoMessage("Category added successfully.");
}

function deleteCategory(id) {
    const customCategories = getCustomCategories();
    const updatedCategories = customCategories.filter(function (category) {
        return category.id !== id;
    });

    saveCustomCategories(updatedCategories);
    initializeCategoriesPage();
    showDemoMessage("Category removed.");
}

function initializeCategoriesPage() {
    const grid = document.getElementById("categoryGrid");

    if (!grid) {
        return;
    }

    const builtInCategories = [
        { name: "Food", type: "expense", icon: "♧", className: "food" },
        { name: "Hostel / Rent", type: "expense", icon: "⌂", className: "rent" },
        { name: "Transport", type: "expense", icon: "▣", className: "transport" },
        { name: "Academics", type: "expense", icon: "▤", className: "academic" },
        { name: "Subscriptions", type: "expense", icon: "◇", className: "subscription" },
        { name: "Allowance", type: "income", icon: "↑", className: "income" },
        { name: "Salary / Part-time", type: "income", icon: "↗", className: "income" },
        { name: "Scholarship", type: "income", icon: "★", className: "income" },
        { name: "Gift", type: "income", icon: "♡", className: "income" }
    ];

    const customCategories = getCustomCategories();
    const categories = builtInCategories.concat(
        customCategories.map(function (category) {
            return {
                name: category.name,
                type: category.type,
                icon: category.type === "income" ? "↑" : "•",
                className: category.type === "income" ? "income" : "other",
                custom: true,
                id: category.id
            };
        })
    );

    grid.innerHTML = "";

    categories.forEach(function (category) {
        const card = document.createElement("div");
        card.className = "category-panel";

        const icon = document.createElement("div");
        icon.className = "big-cat " + category.className;
        icon.textContent = category.icon;

        const title = document.createElement("h3");
        title.textContent = category.name;

        const type = document.createElement("p");
        type.textContent = category.type === "income" ? "Income category" : "Expense category";

        const label = document.createElement("strong");
        label.textContent = category.custom ? "Custom category" : "Default category";

        card.appendChild(icon);
        card.appendChild(title);
        card.appendChild(type);
        card.appendChild(label);

        if (category.custom) {
            const deleteButton = document.createElement("button");
            deleteButton.className = "category-delete";
            deleteButton.type = "button";
            deleteButton.innerHTML = "× Delete";
            deleteButton.addEventListener("click", function () {
                deleteCategory(category.id);
            });
            card.appendChild(deleteButton);
        }

        grid.appendChild(card);
    });
}


function getBudgets() {
    const currentKey = getCurrentMonthKey();
    const saved = localStorage.getItem("campusCoinBudgets");
    let allBudgets = [];

    if (saved) {
        try {
            allBudgets = JSON.parse(saved);
        } catch (error) {
            allBudgets = [];
        }
    }

    allBudgets = allBudgets.map(function (budget) {
        if (!budget.monthKey) {
            budget.monthKey = currentKey;
        }
        return budget;
    });

    const currentBudgets = allBudgets.filter(function (budget) {
        return budget.monthKey === currentKey;
    });

    if (currentBudgets.length === 0) {
        const defaults = [
            { id: Date.now() + 1, category: "Food", amount: 90, monthKey: currentKey },
            { id: Date.now() + 2, category: "Transport", amount: 50, monthKey: currentKey },
            { id: Date.now() + 3, category: "Academics", amount: 40, monthKey: currentKey },
            { id: Date.now() + 4, category: "Subscriptions", amount: 25, monthKey: currentKey }
        ];

        saveBudgets(defaults, allBudgets);
        return defaults;
    }

    return currentBudgets;
}

function saveBudgets(budgets, existingBudgets) {
    const currentKey = getCurrentMonthKey();
    let allBudgets = existingBudgets;

    if (!allBudgets) {
        const saved = localStorage.getItem("campusCoinBudgets");

        if (saved) {
            try {
                allBudgets = JSON.parse(saved);
            } catch (error) {
                allBudgets = [];
            }
        } else {
            allBudgets = [];
        }
    }

    const history = allBudgets.filter(function (budget) {
        return budget.monthKey && budget.monthKey !== currentKey;
    });

    const current = budgets.map(function (budget) {
        budget.monthKey = currentKey;
        return budget;
    });

    localStorage.setItem("campusCoinBudgets", JSON.stringify(history.concat(current)));
}

function getBudgetSpent(category) {
    const transactions = getTransactions();
    let total = 0;

    transactions.forEach(function (transaction) {
        if (transaction.type === "expense" && transaction.category === category) {
            total += Number(transaction.amount);
        }
    });

    return total;
}

function updateBudgetCategoryOptions(selectedCategory) {
    const select = document.getElementById("budgetCategory");

    if (!select) {
        return;
    }

    select.innerHTML = "";

    const categories = getBuiltInCategories("expense").slice();
    const customCategories = getCustomCategories();

    customCategories.forEach(function (category) {
        if (category.type === "expense" && !categories.includes(category.name)) {
            categories.push(category.name);
        }
    });

    const budgets = getBudgets();

    categories.forEach(function (category) {
        const alreadyUsed = budgets.some(function (budget) {
            return budget.category === category && budget.category !== selectedCategory;
        });

        if (alreadyUsed) {
            return;
        }

        const option = document.createElement("option");
        option.value = category;
        option.textContent = category;
        select.appendChild(option);
    });

    if (selectedCategory) {
        select.value = selectedCategory;
    }
}

function openBudgetModal(budgetId) {
    const modal = document.getElementById("budgetModal");
    const form = document.getElementById("budgetForm");
    const idInput = document.getElementById("budgetId");
    const amountInput = document.getElementById("budgetAmount");
    const title = document.getElementById("budgetModalTitle");
    const eyebrow = document.getElementById("budgetModalEyebrow");

    if (!modal) {
        return;
    }

    if (form) {
        form.reset();
    }

    const budgets = getBudgets();
    const existing = budgets.find(function (budget) {
        return String(budget.id) === String(budgetId);
    });

    if (existing) {
        idInput.value = existing.id;
        amountInput.value = existing.amount;
        title.textContent = "Edit budget";
        eyebrow.textContent = "Update your monthly limit";
        updateBudgetCategoryOptions(existing.category);
    } else {
        idInput.value = "";
        title.textContent = "Add budget";
        eyebrow.textContent = "Monthly budget";
        updateBudgetCategoryOptions();
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function closeBudgetModal() {
    const modal = document.getElementById("budgetModal");

    if (!modal) {
        return;
    }

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
}

function saveBudget() {
    const idInput = document.getElementById("budgetId");
    const categoryInput = document.getElementById("budgetCategory");
    const amountInput = document.getElementById("budgetAmount");

    const id = idInput.value;
    const category = categoryInput.value;
    const amount = Number(amountInput.value);

    if (!category || !amount || amount <= 0) {
        showDemoMessage("Please enter a valid budget.");
        return;
    }

    const budgets = getBudgets();

    if (id) {
        const budget = budgets.find(function (item) {
            return String(item.id) === String(id);
        });

        if (budget) {
            budget.category = category;
            budget.amount = amount;
        }

        showDemoMessage("Budget updated successfully.");
    } else {
        const exists = budgets.some(function (item) {
            return item.category === category;
        });

        if (exists) {
            showDemoMessage("A budget already exists for this category.");
            return;
        }

        budgets.push({
            id: Date.now(),
            category: category,
            amount: amount
        });

        showDemoMessage("Budget added successfully.");
    }

    saveBudgets(budgets);
    closeBudgetModal();
    initializeBudgetsPage();
}

function deleteBudget(id) {
    const budgets = getBudgets();
    const updated = budgets.filter(function (budget) {
        return String(budget.id) !== String(id);
    });

    saveBudgets(updated);
    initializeBudgetsPage();
    showDemoMessage("Budget removed.");
}

function initializeBudgetsPage() {
    const grid = document.getElementById("budgetGrid");

    if (!grid) {
        return;
    }

    if (grid.children.length > 0) {
        return;
    }

    const budgets = getBudgets();

    if (budgets.length === 0) {
        grid.innerHTML = '<div class="transaction-empty">No budgets yet. Click “New budget” to add one.</div>';
        return;
    }

    grid.innerHTML = "";

    budgets.forEach(function (budget) {
        const spent = getBudgetSpent(budget.category);
        const limit = Number(budget.amount);
        const remaining = limit - spent;
        const percentage = limit > 0 ? Math.min((spent / limit) * 100, 100) : 0;

        const card = document.createElement("div");
        card.className = "budget-panel";

        const top = document.createElement("div");
        top.className = "budget-top";

        const name = document.createElement("span");
        name.textContent = budget.category;

        const amount = document.createElement("b");
        amount.textContent = "Rs. " + spent.toFixed(2) + " / Rs. " + limit.toFixed(2);

        top.appendChild(name);
        top.appendChild(amount);

        const track = document.createElement("div");
        track.className = "progress-track";

        const fill = document.createElement("span");
        fill.style.width = percentage.toFixed(0) + "%";
        track.appendChild(fill);

        const remainingText = document.createElement("small");
        remainingText.textContent = remaining >= 0
            ? "Rs. " + remaining.toFixed(2) + " remaining"
            : "Rs. " + Math.abs(remaining).toFixed(2) + " over budget";

        const actions = document.createElement("div");
        actions.className = "budget-actions";

        const editButton = document.createElement("button");
        editButton.type = "button";
        editButton.className = "budget-edit";
        editButton.textContent = "Edit";
        editButton.addEventListener("click", function () {
            openBudgetModal(budget.id);
        });

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "budget-delete";
        deleteButton.textContent = "Delete";
        deleteButton.addEventListener("click", function () {
            deleteBudget(budget.id);
        });

        actions.appendChild(editButton);
        actions.appendChild(deleteButton);

        card.appendChild(top);
        card.appendChild(track);
        card.appendChild(remainingText);
        card.appendChild(actions);

        grid.appendChild(card);
    });
}

function initializeReportsPage() {
    const reportIncome = document.getElementById("reportIncome");
    const reportExpense = document.getElementById("reportExpense");
    const reportBalance = document.getElementById("reportBalance");
    const reportBreakdown = document.getElementById("reportBreakdown");

    if (!reportIncome || !reportExpense || !reportBalance) {
        return;
    }

    const transactions = getAllTransactions();
    let income = 0;
    let expense = 0;
    const categoryTotals = {};

    transactions.forEach(function (transaction) {
        const amount = Number(transaction.amount);

        if (transaction.type === "income") {
            income += amount;
        } else {
            expense += amount;

            if (!categoryTotals[transaction.category]) {
                categoryTotals[transaction.category] = 0;
            }

            categoryTotals[transaction.category] += amount;
        }
    });

    const balance = income - expense;
    const savingsRate = income > 0 ? Math.round((balance / income) * 100) : 0;

    reportIncome.textContent = "Rs. " + income.toFixed(2);
    reportExpense.textContent = "Rs. " + expense.toFixed(2);
    reportBalance.textContent = "Rs. " + balance.toFixed(2);

    const countElement = document.getElementById("reportExpenseCount");
    const savingsElement = document.getElementById("reportSavings");

    if (countElement) {
        const count = transactions.filter(function (transaction) {
            return transaction.type === "expense";
        }).length;
        countElement.textContent = count + (count === 1 ? " transaction" : " transactions");
    }

    if (savingsElement) {
        savingsElement.textContent = savingsRate + "% of income saved";
    }

    if (reportBreakdown) {
        const entries = Object.entries(categoryTotals).sort(function (a, b) {
            return b[1] - a[1];
        });

        if (entries.length === 0) {
            reportBreakdown.innerHTML = '<div class="transaction-empty">No expenses recorded yet.</div>';
        } else {
            reportBreakdown.innerHTML = "";

            entries.forEach(function (entry) {
                const row = document.createElement("div");
                const name = document.createElement("span");
                const value = document.createElement("b");

                name.textContent = entry[0];
                value.textContent = "Rs. " + entry[1].toFixed(2);

                row.appendChild(name);
                row.appendChild(value);
                reportBreakdown.appendChild(row);
            });
        }
    }

    updateReportChart(transactions);
}

function updateReportChart(transactions) {
    const chartElement = document.getElementById("reportChart");

    if (!chartElement || typeof Chart === "undefined") {
        return;
    }

    if (window.campusCoinReportChart) {
        window.campusCoinReportChart.destroy();
    }

    const labels = [];
    const incomeData = [];
    const expenseData = [];
    const now = new Date();

    for (let i = 5; i >= 0; i--) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
        labels.push(date.toLocaleDateString("en-US", { month: "short" }));
        incomeData.push(0);
        expenseData.push(0);
    }

    transactions.forEach(function (transaction) {
        const date = new Date(transaction.date + "T00:00:00");
        const monthDifference = (now.getFullYear() - date.getFullYear()) * 12 + now.getMonth() - date.getMonth();

        if (monthDifference < 0 || monthDifference > 5) {
            return;
        }

        const index = 5 - monthDifference;
        const amount = Number(transaction.amount);

        if (transaction.type === "income") {
            incomeData[index] += amount;
        } else {
            expenseData[index] += amount;
        }
    });

    window.campusCoinReportChart = new Chart(chartElement, {
        type: "bar",
        data: {
            labels: labels,
            datasets: [
                {
                    label: "Income",
                    data: incomeData,
                    borderWidth: 1
                },
                {
                    label: "Expense",
                    data: expenseData,
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function getCategoryClass(category) {
    const classes = {
        Food: "food",
        Transport: "transport",
        Academics: "academic",
        "Hostel/Rent": "hostel",
        Subscriptions: "subscription",
        Allowance: "income",
        Other: "other"
    };

    return classes[category] || "other";
}

function getCategoryIcon(category) {
    const icons = {
        Food: "♧",
        Transport: "▣",
        Academics: "▤",
        "Hostel/Rent": "⌂",
        Subscriptions: "☆",
        Allowance: "↑",
        Other: "•"
    };

    return icons[category] || "•";
}

function getTodayDate() {
    const date = new Date();
    return date.toISOString().split("T")[0];
}

function getYesterdayDate() {
    return getDaysAgoDate(1);
}

function getDaysAgoDate(days) {
    const date = new Date();
    date.setDate(date.getDate() - days);
    return date.toISOString().split("T")[0];
}

function formatDate(dateString) {
    const date = new Date(dateString + "T00:00:00");
    const today = new Date();
    const yesterday = new Date();

    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return "Today";
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return "Yesterday";
    }

    return date.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric"
    });
}

function updateCurrentDateTime() {
    const element = document.getElementById("currentDateTime");

    if (!element) {
        return;
    }

    const now = new Date();
    const dateText = now.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric"
    });
    const timeText = now.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit"
    });

    element.textContent = dateText + " · " + timeText;
}

function initializeMonthlyCycle() {
    const currentKey = getCurrentMonthKey();
    const lastOpened = localStorage.getItem("campusCoinLastOpenedMonth");

    if (lastOpened && lastOpened !== currentKey) {
        showDemoMessage("New month started. Your new monthly cycle is ready.");
    }

    localStorage.setItem("campusCoinLastOpenedMonth", currentKey);

    document.querySelectorAll("[data-current-month]").forEach(function (element) {
        element.textContent = getCurrentMonthName();
    });
}

function showDemoMessage(message) {
    let toast = document.querySelector(".demo-toast");

    if (!toast) {
        toast = document.createElement("div");
        toast.className = "demo-toast";
        document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(function () {
        toast.classList.remove("show");
    }, 2800);
}

window.openTransactionModal = openTransactionModal;
window.closeTransactionModal = closeTransactionModal;
window.initializeDashboard = initializeDashboard;
window.initializeSavingTip = initializeSavingTip;
window.pinSavingTip = pinSavingTip;
window.dismissSavingTip = dismissSavingTip;
window.openCategoryModal = openCategoryModal;
window.closeCategoryModal = closeCategoryModal;
window.initializeCategoriesPage = initializeCategoriesPage;
window.openBudgetModal = openBudgetModal;
window.closeBudgetModal = closeBudgetModal;
window.initializeBudgetsPage = initializeBudgetsPage;
window.initializeReportsPage = initializeReportsPage;

function getCampusUser() {
    const savedUser = localStorage.getItem("campusCoinUser");

    if (savedUser) {
        try {
            return JSON.parse(savedUser);
        } catch (error) {
            return { name: "Student", email: "" };
        }
    }

    return { name: "Student", email: "" };
}

function initializeUserGreeting() {
    const user = getCampusUser();
    const welcomeName = document.getElementById("welcomeName");

    if (welcomeName) {
        const firstName = user.name ? user.name.split(" ")[0] : "Student";
        const hour = new Date().getHours();
        let greeting = "Good morning";

        if (hour >= 12 && hour < 18) {
            greeting = "Good afternoon";
        } else if (hour >= 18) {
            greeting = "Good evening";
        }

        welcomeName.textContent = greeting + ", " + firstName + " 👋";
    }

    const avatar = document.querySelector(".avatar");

    if (avatar) {
        const initials = user.name
            ? user.name.split(" ").map(function (part) { return part.charAt(0); }).slice(0, 2).join("").toUpperCase()
            : "ST";
        avatar.textContent = initials;
    }
}

function setDailyLimit() {
    const current = getDailyLimit();
    const value = window.prompt("Enter your daily spending limit in PKR:", current);
    const limit = Number(value);

    if (!value || !limit || limit <= 0) {
        return;
    }

    localStorage.setItem("campusCoinDailyLimit", String(limit));
    updateDailyLimitCard(getTransactions());
    showDemoMessage("Daily spending limit updated.");
}

function getDailyLimit() {
    const savedLimit = localStorage.getItem("campusCoinDailyLimit");

    if (savedLimit) {
        return Number(savedLimit);
    }

    return 1000;
}

function updateDailyLimitCard(transactions) {
    const limitText = document.getElementById("dailyLimitText");
    const statusText = document.getElementById("dailyLimitStatus");

    if (!limitText || !statusText) {
        return;
    }

    const today = getTodayDate();
    let todayExpense = 0;

    transactions.forEach(function (transaction) {
        if (transaction.type === "expense" && transaction.date === today) {
            todayExpense += Number(transaction.amount);
        }
    });

    const limit = getDailyLimit();
    const remaining = limit - todayExpense;

    limitText.textContent = "Rs. " + limit.toFixed(2) + " / day";

    if (remaining >= 0) {
        statusText.textContent = "Rs. " + remaining.toFixed(2) + " left today";
    } else {
        statusText.textContent = "Rs. " + Math.abs(remaining).toFixed(2) + " over today's limit";
    }
}

function getBudgetAlerts() {
    const budgets = getBudgets();
    const alerts = [];

    budgets.forEach(function (budget) {
        const spent = getBudgetSpent(budget.category);
        const limit = Number(budget.amount);

        if (limit <= 0) {
            return;
        }

        const percentage = spent / limit;

        if (percentage >= 1) {
            alerts.push(budget.category + " budget has been exceeded.");
        } else if (percentage >= 0.8) {
            alerts.push(budget.category + " budget is above 80%.");
        }
    });

    return alerts;
}

function renderBudgetAlerts() {
    const container = document.getElementById("budgetAlerts");

    if (!container) {
        return;
    }

    const alerts = getBudgetAlerts();

    if (alerts.length === 0) {
        container.innerHTML = '<div class="alert-good">✓ Your budgets are currently on track.</div>';
        return;
    }

    container.innerHTML = "";

    alerts.forEach(function (alert) {
        const item = document.createElement("div");
        item.className = "budget-alert";
        item.textContent = "⚠ " + alert;
        container.appendChild(item);
    });
}

function updateDashboardSavingsComparison(transactions) {
    const element = document.getElementById("monthlyComparison");

    if (!element) {
        return;
    }

    const now = new Date();
    let currentIncome = 0;
    let currentExpense = 0;
    let previousIncome = 0;
    let previousExpense = 0;

    transactions.forEach(function (transaction) {
        const date = new Date(transaction.date + "T00:00:00");
        const monthDifference = (now.getFullYear() - date.getFullYear()) * 12 + now.getMonth() - date.getMonth();
        const amount = Number(transaction.amount);

        if (monthDifference === 0) {
            if (transaction.type === "income") {
                currentIncome += amount;
            } else {
                currentExpense += amount;
            }
        }

        if (monthDifference === 1) {
            if (transaction.type === "income") {
                previousIncome += amount;
            } else {
                previousExpense += amount;
            }
        }
    });

    const currentBalance = currentIncome - currentExpense;
    const previousBalance = previousIncome - previousExpense;

    element.textContent = "This month: Rs. " + currentBalance.toFixed(2) + " saved · Last month: Rs. " + previousBalance.toFixed(2);
}

function openTransactionModal(type, transactionId) {
    const modal = document.getElementById("transactionModal");
    const typeInput = document.getElementById("transactionType");
    const idInput = document.getElementById("transactionId");
    const title = document.getElementById("transactionModalTitle");
    const eyebrow = document.getElementById("transactionModalEyebrow");
    const form = document.getElementById("transactionForm");
    const titleInput = document.getElementById("transactionTitle");
    const amountInput = document.getElementById("transactionAmount");
    const categorySelect = document.getElementById("transactionCategory");
    const dateInput = document.getElementById("transactionDate");
    const timeInput = document.getElementById("transactionTime");
    const saveButton = document.getElementById("transactionSaveButton");

    if (!modal || !typeInput || !title || !eyebrow) {
        return;
    }

    if (form) {
        form.reset();
    }

    let existing = null;

    if (transactionId) {
        existing = getTransactions().find(function (transaction) {
            return String(transaction.id) === String(transactionId);
        });
    }

    if (existing) {
        type = existing.type;
        idInput.value = existing.id;
        titleInput.value = existing.title;
        amountInput.value = existing.amount;
        dateInput.value = existing.date;
        if (timeInput) {
            timeInput.value = existing.time || "12:00";
        }
        eyebrow.textContent = "Update transaction";
        title.textContent = "Edit " + type;
        saveButton.textContent = "Update transaction";
    } else {
        idInput.value = "";
        typeInput.value = type;
        eyebrow.textContent = type === "income" ? "Money coming in" : "Money going out";
        title.textContent = type === "income" ? "Add income" : "Add expense";
        titleInput.placeholder = type === "income" ? "e.g. Monthly allowance" : "e.g. Lunch at campus cafe";
        amountInput.value = "";
        dateInput.value = getTodayDate();
        if (timeInput) {
            timeInput.value = getCurrentTime();
        }
        saveButton.textContent = "Save transaction";
    }

    typeInput.value = type;
    updateTransactionCategories(type);

    if (existing && categorySelect) {
        categorySelect.value = existing.category;
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function saveTransaction() {
    const id = document.getElementById("transactionId") ? document.getElementById("transactionId").value : "";
    const type = document.getElementById("transactionType").value;
    const title = document.getElementById("transactionTitle").value.trim();
    const amount = Number(document.getElementById("transactionAmount").value);
    const category = document.getElementById("transactionCategory").value;
    const date = document.getElementById("transactionDate").value;
    const timeInput = document.getElementById("transactionTime");
    const time = timeInput ? timeInput.value : getCurrentTime();

    if (!title || !amount || amount <= 0 || !category || !date) {
        showDemoMessage("Please fill all transaction fields.");
        return;
    }

    const transactions = getTransactions();

    if (id) {
        const existing = transactions.find(function (transaction) {
            return String(transaction.id) === String(id);
        });

        if (existing) {
            existing.type = type;
            existing.title = title;
            existing.amount = amount;
            existing.category = category;
            existing.date = date;
            existing.time = time;
            existing.monthKey = getMonthKeyFromDate(date);
        }

        showDemoMessage("Transaction updated successfully.");
    } else {
        transactions.unshift({
            id: Date.now(),
            type: type,
            title: title,
            amount: amount,
            category: category,
            date: date,
            time: time,
            monthKey: getMonthKeyFromDate(date)
        });

        showDemoMessage(type === "income" ? "Income added successfully." : "Expense added successfully.");
    }

    saveTransactions(transactions);
    closeTransactionModal();
    initializeDashboard();
    initializeTransactionsPage();
    initializeReportsPage();
    initializeBudgetsPage();
    renderBudgetAlerts();
}

function renderFullTransactions() {
    const list = document.getElementById("fullTransactionList");

    if (!list) {
        return;
    }

    const search = (document.getElementById("transactionSearch")?.value || "").toLowerCase().trim();
    const type = document.getElementById("transactionFilterType")?.value || "all";
    const category = document.getElementById("transactionFilterCategory")?.value || "all";

    const transactions = getTransactions().filter(function (transaction) {
        const matchesSearch = !search || transaction.title.toLowerCase().includes(search) || transaction.category.toLowerCase().includes(search);
        const matchesType = type === "all" || transaction.type === type;
        const matchesCategory = category === "all" || transaction.category === category;
        return matchesSearch && matchesType && matchesCategory;
    });

    const countLabel = document.getElementById("transactionCountLabel");

    if (countLabel) {
        countLabel.textContent = transactions.length + (transactions.length === 1 ? " transaction" : " transactions");
    }

    list.innerHTML = "";

    if (transactions.length === 0) {
        list.innerHTML = '<div class="transaction-empty">No matching transactions found.</div>';
        return;
    }

    transactions.forEach(function (transaction) {
        const row = document.createElement("div");
        row.className = "full-transaction-row";

        const info = document.createElement("div");
        const name = document.createElement("strong");
        const details = document.createElement("span");
        name.textContent = transaction.title;
        details.textContent = transaction.category + " · " + formatDate(transaction.date) + " · " + formatTime(transaction.time);
        info.appendChild(name);
        info.appendChild(details);

        const amount = document.createElement("b");
        amount.className = transaction.type === "income" ? "income" : "expense";
        amount.textContent = (transaction.type === "income" ? "+Rs. " : "-Rs. ") + Number(transaction.amount).toFixed(2);

        const actions = document.createElement("div");
        actions.className = "row-actions";

        const edit = document.createElement("button");
        edit.className = "outline-btn small-action";
        edit.textContent = "Edit";
        edit.type = "button";
        edit.onclick = function () { openTransactionModal(transaction.type, transaction.id); };

        const remove = document.createElement("button");
        remove.className = "transaction-delete";
        remove.textContent = "Delete";
        remove.type = "button";
        remove.onclick = function () { deleteTransaction(transaction.id); };

        actions.appendChild(edit);
        actions.appendChild(remove);
        row.appendChild(info);
        row.appendChild(amount);
        row.appendChild(actions);
        list.appendChild(row);
    });
}

function initializeTransactionFilters() {
    const categorySelect = document.getElementById("transactionFilterCategory");

    if (!categorySelect) {
        return;
    }

    const names = [];
    getBuiltInCategories("income").concat(getBuiltInCategories("expense")).forEach(function (name) {
        if (!names.includes(name)) {
            names.push(name);
        }
    });

    getCustomCategories().forEach(function (category) {
        if (!names.includes(category.name)) {
            names.push(category.name);
        }
    });

    categorySelect.innerHTML = '<option value="all">All categories</option>';

    names.sort().forEach(function (name) {
        const option = document.createElement("option");
        option.value = name;
        option.textContent = name;
        categorySelect.appendChild(option);
    });
}

function clearTransactionFilters() {
    document.getElementById("transactionSearch").value = "";
    document.getElementById("transactionFilterType").value = "all";
    document.getElementById("transactionFilterCategory").value = "all";
    renderFullTransactions();
}

function initializeTransactionsPage() {
    if (!document.getElementById("fullTransactionList")) {
        return;
    }

    initializeTransactionFilters();
    renderFullTransactions();

    ["transactionSearch", "transactionFilterType", "transactionFilterCategory"].forEach(function (id) {
        const element = document.getElementById(id);
        if (element && !element.dataset.bound) {
            element.addEventListener("input", renderFullTransactions);
            element.addEventListener("change", renderFullTransactions);
            element.dataset.bound = "true";
        }
    });

    initializeRecurringTransactions();
}

function getRecurringTransactions() {
    const saved = localStorage.getItem("campusCoinRecurring");

    if (!saved) {
        return [];
    }

    try {
        return JSON.parse(saved);
    } catch (error) {
        return [];
    }
}

function saveRecurringTransactions(items) {
    localStorage.setItem("campusCoinRecurring", JSON.stringify(items));
}

function openRecurringModal() {
    const modal = document.getElementById("recurringModal");
    if (!modal) return;
    document.getElementById("recurringForm").reset();
    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function closeRecurringModal() {
    const modal = document.getElementById("recurringModal");
    if (!modal) return;
    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
}

function initializeRecurringTransactions() {
    const list = document.getElementById("recurringList");
    const form = document.getElementById("recurringForm");

    if (!list) return;

    const items = getRecurringTransactions();
    list.innerHTML = "";

    if (items.length === 0) {
        list.innerHTML = '<div class="transaction-empty">No recurring transactions yet.</div>';
    } else {
        items.forEach(function (item) {
            const row = document.createElement("div");
            row.className = "full-transaction-row";
            row.innerHTML = '<div><strong>' + escapeHtml(item.title) + '</strong><span>Monthly · ' + item.type + '</span></div><b class="' + item.type + '">' + (item.type === "income" ? "+" : "-") + 'Rs. ' + Number(item.amount).toFixed(2) + '</b><button class="transaction-delete" type="button">Delete</button>';
            row.querySelector("button").onclick = function () {
                saveRecurringTransactions(items.filter(function (saved) { return saved.id !== item.id; }));
                initializeRecurringTransactions();
            };
            list.appendChild(row);
        });
    }

    if (form && !form.dataset.bound) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            const savedItems = getRecurringTransactions();
            savedItems.push({
                id: Date.now(),
                title: document.getElementById("recurringTitle").value.trim(),
                amount: Number(document.getElementById("recurringAmount").value),
                type: document.getElementById("recurringType").value
            });
            saveRecurringTransactions(savedItems);
            closeRecurringModal();
            initializeRecurringTransactions();
            showDemoMessage("Recurring transaction added.");
        });
        form.dataset.bound = "true";
    }
}

function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, function (character) {
        const entities = { "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" };
        return entities[character];
    });
}

function getSavingsGoals() {
    const saved = localStorage.getItem("campusCoinSavingsGoals");

    if (saved) {
        try {
            return JSON.parse(saved);
        } catch (error) {
            return [];
        }
    }

    const defaults = [{ id: 1, name: "Emergency fund", target: 40000, saved: 24800 }];
    localStorage.setItem("campusCoinSavingsGoals", JSON.stringify(defaults));
    return defaults;
}

function saveSavingsGoals(goals) {
    localStorage.setItem("campusCoinSavingsGoals", JSON.stringify(goals));
}

function openGoalModal(goalId) {
    const modal = document.getElementById("goalModal");
    const form = document.getElementById("goalForm");
    if (!modal) return;
    form.reset();
    document.getElementById("goalId").value = "";
    document.getElementById("goalAddAmount").value = "";
    document.getElementById("goalAddAmountWrap").style.display = "none";
    document.getElementById("goalModalTitle").textContent = "Add savings goal";

    if (goalId) {
        const goal = getSavingsGoals().find(function (item) { return String(item.id) === String(goalId); });
        if (goal) {
            document.getElementById("goalId").value = goal.id;
            document.getElementById("goalName").value = goal.name;
            document.getElementById("goalTarget").value = goal.target;
            document.getElementById("goalSaved").value = goal.saved;
            document.getElementById("goalAddAmountWrap").style.display = "block";
            document.getElementById("goalModalTitle").textContent = "Edit savings goal";
        }
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function closeGoalModal() {
    const modal = document.getElementById("goalModal");
    if (!modal) return;
    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
}

function initializeGoalsPage() {
    const grid = document.getElementById("goalGrid");
    const form = document.getElementById("goalForm");
    if (!grid) return;

    const goals = getSavingsGoals();
    grid.innerHTML = "";

    goals.forEach(function (goal) {
        const percentage = goal.target > 0 ? Math.min((goal.saved / goal.target) * 100, 100) : 0;
        const card = document.createElement("div");
        card.className = "goal-panel";
        card.innerHTML = '<div class="goal-panel-top"><div><p class="card-label">Savings goal</p><h3>' + escapeHtml(goal.name) + '</h3></div><strong>' + percentage.toFixed(0) + '%</strong></div><div class="progress-track"><span style="width:' + percentage.toFixed(0) + '%"></span></div><p>Rs. ' + Number(goal.saved).toFixed(2) + ' saved of Rs. ' + Number(goal.target).toFixed(2) + '</p><div class="budget-actions"><button class="goal-add-money" type="button">+ Add money</button><button class="budget-edit" type="button">Edit</button><button class="budget-delete" type="button">Delete</button></div>';
        card.querySelector(".goal-add-money").onclick = function () { openGoalModal(goal.id); document.getElementById("goalAddAmount").focus(); };
        card.querySelector(".budget-edit").onclick = function () { openGoalModal(goal.id); };
        card.querySelector(".budget-delete").onclick = function () {
            saveSavingsGoals(goals.filter(function (item) { return item.id !== goal.id; }));
            initializeGoalsPage();
            showDemoMessage("Savings goal removed.");
        };
        grid.appendChild(card);
    });

    if (goals.length === 0) {
        grid.innerHTML = '<div class="transaction-empty">No savings goals yet. Create your first goal.</div>';
    }

    if (form && !form.dataset.bound) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            const id = document.getElementById("goalId").value;
            const goalsNow = getSavingsGoals();
            const existingGoal = id ? goalsNow.find(function (goal) { return String(goal.id) === String(id); }) : null;
            const addAmount = Number(document.getElementById("goalAddAmount").value || 0);
            const data = {
                id: id ? Number(id) : Date.now(),
                name: document.getElementById("goalName").value.trim(),
                target: Number(document.getElementById("goalTarget").value),
                saved: existingGoal && addAmount > 0 ? Number(existingGoal.saved) + addAmount : Number(document.getElementById("goalSaved").value)
            };

            if (id) {
                const existing = goalsNow.find(function (goal) { return String(goal.id) === String(id); });
                if (existing) Object.assign(existing, data);
            } else {
                goalsNow.push(data);
            }

            saveSavingsGoals(goalsNow);
            closeGoalModal();
            initializeGoalsPage();
            showDemoMessage(id ? "Savings goal updated." : "Savings goal added.");
        });
        form.dataset.bound = "true";
    }
}

function exportTransactionsCSV() {
    const transactions = getAllTransactions();
    const rows = [["Date", "Time", "Type", "Title", "Category", "Amount"]];

    transactions.forEach(function (transaction) {
        rows.push([transaction.date, transaction.time || "12:00", transaction.type, transaction.title, transaction.category, Number(transaction.amount).toFixed(2)]);
    });

    const csv = rows.map(function (row) {
        return row.map(function (cell) {
            return '"' + String(cell).replace(/"/g, '""') + '"';
        }).join(",");
    }).join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "campus-coin-transactions.csv";
    link.click();
    URL.revokeObjectURL(url);
}

const originalInitializeDashboard = initializeDashboard;
initializeDashboard = function () {
    if (document.getElementById("welcomeName")) {
        return;
    }
    originalInitializeDashboard();
    const transactions = getTransactions();
    initializeUserGreeting();
    updateDailyLimitCard(transactions);
    updateDashboardSavingsComparison(transactions);
    renderBudgetAlerts();
};

/* =========================================================
   Campus Coin Professional Motion System
   ========================================================= */

function initializeProfessionalAnimations() {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (reduceMotion) {
        return;
    }

    const revealSelectors = [
        ".page-header",
        ".welcome-row",
        ".stats-row",
        ".dashboard-alert-row",
        ".goal-grid",
        ".chart-card",
        ".form-panel",
        ".table-card",
        ".filter-bar",
        ".transactions-list",
        ".landing-features",
        ".auth-mini-card"
    ];

    const revealElements = [];

    revealSelectors.forEach(function (selector) {
        document.querySelectorAll(selector).forEach(function (element) {
            if (!element.classList.contains("motion-reveal")) {
                element.classList.add("motion-reveal");
            }

            revealElements.push(element);
        });
    });

    if ("IntersectionObserver" in window) {
        const observer = new IntersectionObserver(function (entries, currentObserver) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    currentObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.08,
            rootMargin: "0px 0px -35px 0px"
        });

        revealElements.forEach(function (element) {
            observer.observe(element);
        });
    } else {
        revealElements.forEach(function (element) {
            element.classList.add("is-visible");
        });
    }

    document.querySelectorAll(
        ".stats-row, .dashboard-alert-row, .goal-grid, .landing-features, .transactions-list"
    ).forEach(function (container) {
        container.classList.add("motion-stagger");
    });

    initializeButtonRipples();
}

function initializeButtonRipples() {
    document.querySelectorAll("button, .btn").forEach(function (button) {
        if (button.dataset.rippleReady === "true") {
            return;
        }

        button.dataset.rippleReady = "true";
        button.classList.add("ripple-target");

        button.addEventListener("click", function (event) {
            const rect = button.getBoundingClientRect();
            const ripple = document.createElement("span");
            const size = Math.max(rect.width, rect.height);

            ripple.className = "ripple-effect";
            ripple.style.width = size + "px";
            ripple.style.height = size + "px";
            ripple.style.left = (event.clientX - rect.left - size / 2) + "px";
            ripple.style.top = (event.clientY - rect.top - size / 2) + "px";

            button.appendChild(ripple);

            window.setTimeout(function () {
                ripple.remove();
            }, 600);
        });
    });
}

document.addEventListener("DOMContentLoaded", function () {
    window.setTimeout(initializeProfessionalAnimations, 80);

    // ==========================================
    // ACCESSIBILITY: Font Size Adjuster (SRS 1.6 Page 9)
    // ==========================================
    const fontScales = ["font-scale-sm", "font-scale-md", "font-scale-lg", "font-scale-xl"];
    let currentScaleIdx = 1; // default font-scale-md

    const savedScale = localStorage.getItem("campusCoinFontScale");
    if (savedScale && fontScales.includes(savedScale)) {
        currentScaleIdx = fontScales.indexOf(savedScale);
        document.body.classList.add(savedScale);
    }

    function setFontScale(newIdx) {
        fontScales.forEach(cls => document.body.classList.remove(cls));
        currentScaleIdx = Math.max(0, Math.min(fontScales.length - 1, newIdx));
        const activeClass = fontScales[currentScaleIdx];
        document.body.classList.add(activeClass);
        localStorage.setItem("campusCoinFontScale", activeClass);
    }

    const fontDecBtn = document.getElementById("fontDecreaseBtn");
    const fontResetBtn = document.getElementById("fontResetBtn");
    const fontIncBtn = document.getElementById("fontIncreaseBtn");

    if (fontDecBtn) fontDecBtn.addEventListener("click", () => setFontScale(currentScaleIdx - 1));
    if (fontResetBtn) fontResetBtn.addEventListener("click", () => setFontScale(1));
    if (fontIncBtn) fontIncBtn.addEventListener("click", () => setFontScale(currentScaleIdx + 1));

    // ==========================================
    // NOTIFICATIONS DROPDOWN DRAWER
    // ==========================================
    const notifBtn = document.getElementById("notificationBtn");
    const notifDropdown = document.getElementById("notificationDropdown");
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            const isOpen = notifDropdown.classList.toggle("show");
            notifBtn.setAttribute("aria-expanded", String(isOpen));
        });

        document.addEventListener("click", function (e) {
            if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
                notifDropdown.classList.remove("show");
                notifBtn.setAttribute("aria-expanded", "false");
            }
        });
    }

    // ==========================================
    // AI-DRIVEN CATEGORY AUTO-SUGGESTION (SRS 1.6 Page 7)
    // ==========================================
    const transTitleInput = document.getElementById("transactionTitle");
    const aiNotice = document.getElementById("aiSuggestionNotice");
    const aiText = document.getElementById("aiSuggestedText");
    const categorySelect = document.getElementById("transactionCategory");

    const categoryDictionary = [
        { regex: /cafe|coffee|lunch|dinner|burger|pizza|biryani|tea|chai|food|canteen|snack|mcdonald|kfc/i, category: "Food", type: "expense" },
        { regex: /bus|uber|careem|rikshaw|rickshaw|metro|train|petrol|fuel|ticket|fare|transit|van/i, category: "Transport", type: "expense" },
        { regex: /hostel|rent|mess|room|electricity|roommate|laundry/i, category: "Hostel/Rent", type: "expense" },
        { regex: /book|tuition|stationery|copy|photocopy|pen|exam|fee|course|library/i, category: "Academics", type: "expense" },
        { regex: /netflix|spotify|youtube|icloud|google|prime|hosting|domain|subscription/i, category: "Subscriptions", type: "expense" },
        { regex: /movie|cinema|outing|game|gaming|bowling|concert|trip/i, category: "Entertainment", type: "expense" },
        { regex: /allowance|pocket money|family|dad|mom/i, category: "Allowance", type: "income" },
        { regex: /salary|job|freelance|gig|internship|teaching|tutoring/i, category: "Part-time Job", type: "income" },
        { regex: /scholarship|grant|aid|bursary/i, category: "Scholarship", type: "income" },
        { regex: /gift|eidi|present/i, category: "Gift", type: "income" }
    ];

    if (transTitleInput && aiNotice && aiText) {
        transTitleInput.addEventListener("input", function () {
            const query = this.value.trim();
            if (query.length < 3) {
                aiNotice.style.display = "none";
                return;
            }

            let match = categoryDictionary.find(item => item.regex.test(query));
            if (match) {
                aiText.textContent = match.category;
                aiNotice.style.display = "flex";
                aiNotice.dataset.suggestedCategory = match.category;
            } else {
                aiNotice.style.display = "none";
            }
        });
    }

    window.applyAiSuggestion = function () {
        if (!categorySelect || !aiNotice) return;
        const suggested = aiNotice.dataset.suggestedCategory;
        if (!suggested) return;

        for (let i = 0; i < categorySelect.options.length; i++) {
            const optText = categorySelect.options[i].text.toLowerCase();
            const optVal = categorySelect.options[i].value.toLowerCase();
            const sug = suggested.toLowerCase();
            if (optText.includes(sug) || optVal === sug) {
                categorySelect.selectedIndex = i;
                break;
            }
        }
        aiNotice.style.display = "none";
    };
});

// CSV Bulk Import Modal Controls
function openCsvModal() {
    const modal = document.getElementById("csvImportModal");
    if (modal) {
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }
}

function closeCsvModal() {
    const modal = document.getElementById("csvImportModal");
    if (modal) {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
    }
}

function handleCsvFileSelect(input) {
    const info = document.getElementById("csvFileInfo");
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (info) {
            info.style.display = "block";
            info.innerHTML = `<strong>Selected file:</strong> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        }
    }
}

// Share Email Modal Controls
function openShareEmailModal() {
    const modal = document.getElementById("shareEmailModal");
    if (modal) {
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }
}

function closeShareEmailModal() {
    const modal = document.getElementById("shareEmailModal");
    if (modal) {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
    }
}

function handleSendReportEmail(e) {
    e.preventDefault();
    const email = document.getElementById("shareEmailTo").value;
    alert(`Report snapshot dispatched to ${email}.`);
    closeShareEmailModal();
}

