<!-- <nav>
    <div class="ham-menu">
        <span></span>
        <span></span>
        <span></span>
    </div>
</nav> -->
<div class="sidebar">
    <h2 class="app-title">TrackSmart</h2>

    <a href="index.php" class="menu-item">Dashboard</a>
    <a href="add_income.php" class="menu-item">Add Income</a>
    <a href="transactions.php" class="menu-item">Transactions</a>
    <a href="expenses.php" class="menu-item">Budgets</a>
    <a href="reports.php" class="menu-item">Reports</a>
    <a href="todo.php" class="menu-item">To-Do List</a>
    <a href="profile.php" class="menu-item">Settings</a>
    <a href="#" onclick="confirmLogout()" class="menu-item logout">Logout</a>

</div>

<div id="logoutModal" class="logout-overlay">
  <div class="logout-box">
      <h3>Are you sure you want to logout?</h3>
      <div class="logout-actions">
          <button class="cancel-logout" onclick="closeLogout()">Cancel</button>
          <button class="confirm-logout" onclick="proceedLogout()">Yes, Logout</button>
      </div>
  </div>
</div>

<script>
function confirmLogout() {
    document.getElementById("logoutModal").style.display = "flex";
}
function closeLogout() {
    document.getElementById("logoutModal").style.display = "none";
}
function proceedLogout() {
    window.location.href = "logout.php"; 
}
</script>
<!-- <script>
    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.querySelector(".sidebar");

    menuToggle.addEventListener("click", () => {
        sidebar.classList.toggle("active");
    });
</script> -->