// Toggle between login and register forms
function toggleForm(form) {
  document.getElementById("loginForm").style.display =
    form === "login" ? "block" : "none";
  document.getElementById("registerForm").style.display =
    form === "register" ? "block" : "none";
}

// Handle registration
document
  .getElementById("registerFormSubmit")
  .addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = {
      name: document.getElementById("regName").value,
      email: document.getElementById("regEmail").value,
      password: document.getElementById("regPassword").value,
      role: document.getElementById("regRole").value,
    };

    try {
      const response = await fetch("backend/register.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json();
      if (data.success) {
        alert("Registration successful! Please login.");
        toggleForm("login");
      } else {
        alert(data.message || "Registration failed!");
      }
    } catch (error) {
      console.error("Error:", error);
      alert("An error occurred during registration.");
    }
  });

// Handle login
document
  .getElementById("loginFormSubmit")
  .addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = {
      email: document.getElementById("loginEmail").value,
      password: document.getElementById("loginPassword").value,
    };

    try {
      const response = await fetch("backend/login.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json();
      if (data.success) {
        localStorage.setItem("user", JSON.stringify(data.user));
        document.querySelector(".auth-container").style.display = "none";
        document.getElementById("dashboard").style.display = "block";
        loadDashboard(data.user.role);
      } else {
        alert(data.message || "Login failed!");
      }
    } catch (error) {
      console.error("Error:", error);
      alert("An error occurred during login.");
    }
  });

// Load dashboard content
async function loadDashboard(role) {
  const dashboardContent = document.querySelector(".dashboard-content");

  if (role === "student") {
    // Load student dashboard
    dashboardContent.innerHTML = `
            <h2>Welcome Student</h2>
            <div class="card">
                <h3>Your Courses</h3>
                <div id="studentCourses"></div>
            </div>
            <div class="card">
                <h3>Progress</h3>
                <div id="studentProgress"></div>
            </div>
        `;
    loadStudentCourses();
  } else {
    // Load educator dashboard
    dashboardContent.innerHTML = `
            <h2>Welcome Educator</h2>
            <div class="card">
                <h3>Your Courses</h3>
                <button onclick="createCourse()" class="submit-btn">Create New Course</button>
                <div id="educatorCourses"></div>
            </div>
            <div class="card">
                <h3>Student Progress</h3>
                <div id="studentStats"></div>
            </div>
        `;
    loadEducatorCourses();
  }
}

// Load student courses
async function loadStudentCourses() {
  try {
    const response = await fetch("backend/get_courses.php");
    const courses = await response.json();

    const courseList = document.getElementById("studentCourses");
    courseList.innerHTML = courses
      .map(
        (course) => `
            <div class="course-card">
                <h4>${course.name}</h4>
                <p>${course.description}</p>
                <button onclick="enrollCourse(${course.id})">Enroll</button>
            </div>
        `
      )
      .join("");
  } catch (error) {
    console.error("Error loading courses:", error);
  }
}

// Load educator courses
async function loadEducatorCourses() {
  try {
    const response = await fetch("backend/get_educator_courses.php");
    const courses = await response.json();

    const courseList = document.getElementById("educatorCourses");
    courseList.innerHTML = courses
      .map(
        (course) => `
            <div class="course-card">
                <h4>${course.name}</h4>
                <p>${course.description}</p>
                <button onclick="manageCourse(${course.id})">Manage</button>
            </div>
        `
      )
      .join("");
  } catch (error) {
    console.error("Error loading courses:", error);
  }
}

// Logout function
function logout() {
  localStorage.removeItem("user");
  window.location.reload();
}
