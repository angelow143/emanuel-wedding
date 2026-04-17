<script setup>
import { ref, onMounted, nextTick, computed, watch } from 'vue';
import axios from 'axios';

// API Configuration — uses Vite env var in production, falls back to localhost for dev
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

// Global axios timeout — requests fail fast (10s) instead of hanging forever
axios.defaults.timeout = 10000;

// Carousel Key
const carouselKey = ref(0);

// Page Navigation State
const pageTab = ref('home');

const navigatePage = (page) => {
  pageTab.value = page;
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  return new Date(dateString).toLocaleString('en-US', options);
};

const scrollToSection = (id) => {
  if (pageTab.value !== 'home') {
    pageTab.value = 'home';
    setTimeout(() => {
      const el = document.getElementById(id);
      if (el) window.scrollTo({ top: el.offsetTop - 50, behavior: 'smooth' });
    }, 100);
  } else {
    const el = document.getElementById(id);
    if (el) window.scrollTo({ top: el.offsetTop - 50, behavior: 'smooth' });
  }
};

// RSVP / Wish Form State
const rsvpForm = ref({
  name: '',
  category: 'Friends',
  message: '',
  image: null
});

// Auth & Admin State
const authUser = ref(null);
const showLoginPanel = ref(false);
const showLoginPassword = ref(false);
const showAdminPanel = ref(false);
const adminTab = ref('gallery'); // Tracks the active tab in dashboard
const loginForm = ref({ email: '', password: '' });

const checkAuth = async () => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    try {
      const res = await axios.get(`${API_BASE_URL}/user`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      authUser.value = res.data;
    } catch (e) {
      localStorage.removeItem('auth_token');
      authUser.value = null;
    }
  }
};

const handleLogin = async () => {
  try {
    const res = await axios.post(`${API_BASE_URL}/login`, loginForm.value);
    localStorage.setItem('auth_token', res.data.token);
    authUser.value = res.data.user;
    showLoginPanel.value = false;
    showAdminPanel.value = true;
    adminTab.value = 'gallery'; // Fresh start on dashboard
    pageTab.value = 'home'; // Show main content
    loginForm.value = { email: '', password: '' };
    alert("Logged in successfully!");
  } catch (err) {
    if (err.response?.status === 403 && err.response?.data?.message) {
      alert(err.response.data.message);
    } else {
      alert("Invalid credentials.");
    }
  }
};

// Registration state
const showRegisterPanel = ref(false);
const showRegisterPassword = ref(false);
const registerForm = ref({ name: '', email: '', password: '' });

const autoGenerateEmail = () => {
  if (!registerForm.value.name) return;
  const baseName = registerForm.value.name.toLowerCase().replace(/[^a-z0-9]/g, '');
  registerForm.value.email = `${baseName}@wedding.ph`;
};

const generateRandomEmail = () => {
  if (!registerForm.value.name) return;
  const baseName = registerForm.value.name.toLowerCase().replace(/[^a-z0-9]/g, '');
  const randomSuffix = Math.floor(Math.random() * 100);
  registerForm.value.email = `${baseName}${randomSuffix}@wedding.ph`;
};

const handleRegister = async () => {
  try {
    const res = await axios.post(`${API_BASE_URL}/register`, registerForm.value);
    alert("Registration successful! You may now login.");
    showRegisterPanel.value = false;
    showLoginPanel.value = true;
    registerForm.value = { name: '', email: '', password: '' };
  } catch (err) {
    if (err.response?.status === 403 && err.response?.data?.message) {
      alert(err.response.data.message);
    } else if (err.response?.status === 422) {
      alert("Registration failed. Please make sure the email is unique.");
    } else {
      alert("Registration failed.");
    }
  }
};

// Album & Gallery State
const albums = ref([]);
const currentAlbum = ref(null);
const showCreateAlbumForm = ref(false);
const albumForm = ref({ name: '' });
const galleryImages = ref([]); // All photos for main website
const isCreatingAlbum = ref(false);
const isUploadingPhoto = ref(false);

const fetchAlbums = async () => {
  try {
    const res = await axios.get(`${API_BASE_URL}/albums`);
    albums.value = res.data;
  } catch (err) {}
};

const handleCreateAlbum = async () => {
  if (isCreatingAlbum.value) return;
  isCreatingAlbum.value = true;
  try {
    const res = await axios.post(`${API_BASE_URL}/albums`, albumForm.value, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    albums.value.unshift(res.data);
    albumForm.value.name = '';
    showCreateAlbumForm.value = false;
    currentAlbum.value = res.data; // enter the new album
    alert("Album created!");
  } catch (err) {
    alert("Failed to create album.");
  } finally {
    isCreatingAlbum.value = false;
  }
};

const selectAlbum = (album) => {
  currentAlbum.value = album;
};

const handleLogout = async () => {
  try {
    await axios.post(`${API_BASE_URL}/logout`, {}, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
  } catch (err) {}
  localStorage.removeItem('auth_token');
  authUser.value = null;
  showAdminPanel.value = false;
  adminTab.value = 'gallery'; // Reset for next login
  pageTab.value = 'home'; // Go back to main website
  alert("Logged out.");
};

// Edit Profile State
const editProfileForm = ref({ name: '', image: null, password: '' });
const showChangePasswordField = ref(false);
const showProfilePassword = ref(false);

const openEditProfile = () => {
  adminTab.value = 'profile';
  showChangePasswordField.value = false;
  showProfilePassword.value = false;
  editProfileForm.value = {
    name: authUser.value?.name || '',
    image: authUser.value?.image || null,
    password: ''
  };
};

const handleProfileImageUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      editProfileForm.value.image = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

const handleUpdateProfile = async () => {
  try {
    const payload = { name: editProfileForm.value.name };
    if (editProfileForm.value.image) payload.image = editProfileForm.value.image;
    if (editProfileForm.value.password) payload.password = editProfileForm.value.password;
    
    const res = await axios.put(`${API_BASE_URL}/user`, payload, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    
    authUser.value = res.data;
    alert("Profile updated successfully!");
    editProfileForm.value.password = ''; // clear password input
  } catch (err) {
    alert("Failed to update profile.");
  }
};

// Settings State
const settings = ref({ login_enabled: 'true', register_enabled: 'true' });

const fetchSettings = async () => {
  try {
    const res = await axios.get(`${API_BASE_URL}/settings`);
    if (res.data) settings.value = Object.assign(settings.value, res.data);
  } catch (err) {}
};

const toggleLoginSetting = async (e) => {
  const isEnabled = e.target.checked;
  const newValue = isEnabled ? 'true' : 'false';
  settings.value.login_enabled = newValue;
  
  if (authUser.value?.role !== 'admin') return;

  try {
    await axios.post(`${API_BASE_URL}/admin/settings`, { key: 'login_enabled', value: newValue }, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    alert(`System login form is now ${isEnabled ? 'ON' : 'OFF'}.`);
  } catch (err) {
    alert("Failed to update setting.");
  }
};

const toggleRegisterSetting = async (e) => {
  const isEnabled = e.target.checked;
  const newValue = isEnabled ? 'true' : 'false';
  settings.value.register_enabled = newValue;
  
  if (authUser.value?.role !== 'admin') return;

  try {
    await axios.post(`${API_BASE_URL}/admin/settings`, { key: 'register_enabled', value: newValue }, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    alert(`System registration is now ${isEnabled ? 'ON' : 'OFF'}.`);
  } catch (err) {
    alert("Failed to update setting.");
  }
};

// Admin Create User State
const createUserForm = ref({ name: '', email: '', password: '' });
const showAddUserForm = ref(false);
const usersList = ref([]);

const fetchUsersList = async () => {
  if (authUser.value?.role !== 'admin') return;
  try {
    const res = await axios.get(`${API_BASE_URL}/admin/users`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    usersList.value = res.data;
  } catch (err) {}
};

const handleCreateUser = async () => {
  try {
    await axios.post(`${API_BASE_URL}/admin/users`, createUserForm.value, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    alert("User created successfully!");
    createUserForm.value = { name: '', email: '', password: '' };
    fetchUsersList(); // Refresh the list
  } catch (err) {
    alert("Failed to create user. Email may already exist.");
  }
};

const handleChangePassword = async (user) => {
  const newPassword = prompt(`Enter new password for ${user.name}:`);
  if (!newPassword) return; // User cancelled or entered empty
  if (newPassword.length < 6) return alert("Password must be at least 6 characters.");

  try {
    await axios.put(`${API_BASE_URL}/admin/users/${user.id}/password`, { password: newPassword }, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    alert(`Password for ${user.name} changed successfully!`);
  } catch (err) {
    alert("Failed to change password.");
  }
};

// Galleries State
const galleries = ref([]);
const galleryForm = ref({ image: '', title: '' });

const fetchGalleries = async () => {
  try {
    const res = await axios.get(`${API_BASE_URL}/gallery`);
    galleries.value = res.data;
  } catch(err) {
    console.error(err);
  }
};

const handleGalleryUpload = (event) => {
  const files = Array.from(event.target.files);
  if (files.length > 0) {
    galleryForm.value.images = [];
    files.forEach(file => {
      const reader = new FileReader();
      reader.onload = (e) => {
        galleryForm.value.images.push(e.target.result);
        if (files.length === 1) galleryForm.value.image = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  }
};

const handleAddGallery = async () => {
  if (!galleryForm.value.image && (!galleryForm.value.images || !galleryForm.value.images.length)) return alert("Select an image first!");
  if (isUploadingPhoto.value) return;
  isUploadingPhoto.value = true;
  try {
    const payload = { ...galleryForm.value };
    if (currentAlbum.value) payload.album_id = currentAlbum.value.id;
    
    await axios.post(`${API_BASE_URL}/gallery`, payload, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    alert("Uploaded successfully!");
    galleryForm.value = { image: '', images: [], title: '' };
    
    // Refresh for both dashboard and main site
    await fetchAlbums(); // refresh current album photos count/cover
    if (currentAlbum.value && currentAlbum.value.id !== 'timeline') {
      const updated = albums.value.find(a => a.id === currentAlbum.value.id);
      if (updated) currentAlbum.value = updated;
    } else if (currentAlbum.value?.id === 'timeline') {
      await fetchGalleries();
      currentAlbum.value = { id: 'timeline', name: 'Timeline Photos', photos: galleries.value.filter(g => !g.album_id) };
    }
    
    // Refresh galleries view and recreate Magnific Popup bindings safely
    await fetchGalleries();
    await nextTick();
    initGalleryMagnific();
  } catch (err) {
    alert("Upload failed.");
  } finally {
    isUploadingPhoto.value = false;
  }
};

const handleRenameAlbum = async (album) => {
  const newName = prompt("Enter new album name:", album.name);
  if (!newName || newName === album.name) return;
  try {
    await axios.put(`${API_BASE_URL}/albums/${album.id}`, { name: newName }, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    album.name = newName;
    if (currentAlbum.value?.id === album.id) currentAlbum.value.name = newName;
    alert("Renamed successfully!");
  } catch (err) {
    alert("Failed to rename.");
  }
};

const handleDeleteAlbum = async (album) => {
  if (!confirm(`Are you sure you want to delete the album "${album.name}" and ALL its photos? This cannot be undone.`)) return;
  try {
    await axios.delete(`${API_BASE_URL}/albums/${album.id}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    albums.value = albums.value.filter(a => a.id !== album.id);
    if (currentAlbum.value?.id === album.id) currentAlbum.value = null;
    alert("Album deleted.");
    fetchGalleries(); // refresh main gallery too
  } catch (err) {
    alert("Failed to delete album.");
  }
};

const handleDeletePhoto = async (photo) => {
  if (!confirm("Delete this photo?")) return;
  try {
    await axios.delete(`${API_BASE_URL}/gallery/${photo.id}`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    });
    
    // Refresh local state
    if (currentAlbum.value) {
      if (currentAlbum.value.id === 'timeline') {
        myTimelinePhotos.value = myTimelinePhotos.value.filter(p => p.id !== photo.id);
        currentAlbum.value.photos = currentAlbum.value.photos.filter(p => p.id !== photo.id);
      } else {
        const album = albums.value.find(a => a.id === currentAlbum.value.id);
        if (album) {
          album.photos = album.photos.filter(p => p.id !== photo.id);
          currentAlbum.value.photos = currentAlbum.value.photos.filter(p => p.id !== photo.id);
        }
      }
    }
    
    fetchGalleries(); // refresh main gallery
    alert("Photo deleted.");
    initGalleryMagnific();
  } catch (err) {
    alert("Failed to delete photo.");
  }
};

watch(pageTab, (newVal) => {
  if (newVal === 'gallery') {
    fetchGalleries();
    fetchAlbums();
  }
});

const initGalleryMagnific = () => {
  if (window.$ && window.$.fn.magnificPopup) {
    window.$('.image-popup').magnificPopup({
      type: 'image',
      removalDelay: 300,
      mainClass: 'mfp-with-zoom',
      gallery:{
        enabled:true
      },
      zoom: {
        enabled: true,
        duration: 300,
        easing: 'ease-in-out',
        opener: function(openerElement) {
          return openerElement.parent(); 
        }
      }
    });
  }
};

const handleImageUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      rsvpForm.value.image = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

const submitRSVP = async () => {
  if (!rsvpForm.value.name || !rsvpForm.value.message || !rsvpForm.value.image) {
    alert("Please provide your name, a message, and upload a profile photo first! ❤️");
    return;
  }

  try {
    await axios.post(`${API_BASE_URL}/wishes`, rsvpForm.value);
    rsvpForm.value = { name: '', category: 'Friends', message: '', image: null };
    
    // Refresh Wishes
    await fetchWishes();
    
    alert("Thank you! Your wish has been posted.");
    
    // Scroll up to Friends Wishes section (Picture 2)
    scrollToSection('fh5co-testimonial');
  } catch (err) {
    console.error(err);
    alert("Something went wrong. Please try again.");
  }
};

// Wishes State
const wishes = ref([]);
const fetchWishes = async () => {
  try {
    const config = {
      headers: { 
        Accept: 'application/json'
      }
    };
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token.trim()}`;
    }
    const res = await axios.get(`${API_BASE_URL}/wishes`, config);
    wishes.value = res.data;
    carouselKey.value++; 
    
    await nextTick();
    
    // Re-initialize Owl Carousel after Vue mounts the fresh list
    setTimeout(() => {
      if (window.jQuery) {
        window.jQuery('.owl-carousel-fullwidth').owlCarousel({
          items: 1,
          loop: true,
          margin: 0,
          responsiveClass: true,
          nav: false,
          dots: true,
          smartSpeed: 800,
          autoHeight: true
        });
      }
    }, 200);

  } catch (err) {
    console.error(err);
  }
};

const deleteWish = async (id) => {
  if (!id || !confirm("Are you sure you want to delete this wish? This action cannot be undone.")) return;
  try {
    const token = localStorage.getItem('auth_token');
    await axios.delete(`${API_BASE_URL}/wishes/${id}`, {
      headers: { 
        Authorization: `Bearer ${token ? token.trim() : ''}`,
        Accept: 'application/json'
      }
    });
    alert("Wish deleted successfully.");
    await fetchWishes();
  } catch (err) {
    const msg = err.response?.data?.message || "Something went wrong";
    console.error("Delete error:", err);
    alert(`Failed to delete wish: ${msg}`);
  }
};

const toggleWishVisibility = async (id) => {
  if (!id) return;
  try {
    const token = localStorage.getItem('auth_token');
    await axios.patch(`${API_BASE_URL}/wishes/${id}/toggle-visibility`, {}, {
      headers: { 
        Authorization: `Bearer ${token ? token.trim() : ''}`,
        Accept: 'application/json'
      }
    });
    await fetchWishes();
  } catch (err) {
    const msg = err.response?.data?.message || "Something went wrong";
    console.error("Toggle visibility error:", err);
    alert(`Failed to change visibility: ${msg}`);
  }
};

onMounted(async () => {
  // ── STEP 1: Init template FIRST so preloader dismisses immediately ──
  setTimeout(() => {
    // Initialize Countdown
    if (window.simplyCountdown) {
      window.simplyCountdown('.simply-countdown-one', {
        year: 2026,
        month: 3,
        day: 30,
        hours: 8,
        minutes: 0,
        seconds: 0,
        enableUtc: false,
        countUp: true
      });
    }

    // Dismiss preloader immediately — don't wait for API
    if (window.initTemplate) {
      window.initTemplate();
    }

    // Fix for mobile menu links (cloned by jQuery, so Vue listeners are lost)
    if (window.jQuery) {
      const $ = window.jQuery;
      $(document).on('click', '#fh5co-offcanvas a', function(e) {
        const text = $(this).text().trim().toLowerCase();

        // Navigation Mapping
        if (text === 'home') navigatePage('home');
        else if (text === 'gallery') navigatePage('gallery');
        else if (text === 'login') showLoginPanel.value = true;
        else if (text === 'dashboard') showAdminPanel.value = true;
        else if (text === 'story') scrollToSection('fh5co-couple-story');
        else if (text === 'events') scrollToSection('fh5co-event');
        else if (text === 'wishes') scrollToSection('fh5co-testimonial');
        else if (text === 'rsvp') scrollToSection('fh5co-started');

        // Close mobile menu
        $('body').removeClass('offcanvas overflow');
        $('.js-fh5co-nav-toggle').removeClass('active');

        // Prevent default and let Vue handle navigation
        e.preventDefault();
      });
    }

    // Initialize Magnific Popup specifically for the Gallery with correct zoomed opener
    initGalleryMagnific();

  }, 500);

  // ── STEP 2: Fetch all initial data in parallel ──
  // This happens async in the background while the UI has already rendered
  try {
    await Promise.allSettled([
      checkAuth(),
      fetchSettings(),
      fetchWishes(),
      fetchAlbums(),
      fetchGalleries()
    ]);
  } catch (err) {
    console.error('Failed fetching data:', err);
  }
});
const groupedGallery = computed(() => {
  const groups = [];
  
  // 1. Process real albums
  albums.value.forEach(album => {
    if (album.photos && album.photos.length > 0) {
      groups.push({
        id: `album-${album.id}`,
        name: album.name,
        count: album.photos.length,
        image: album.photos[0].image,
        photos: album.photos,
        uploadedBy: album.user?.name || 'Admin',
        user_id: album.user_id,
        isAlbum: true
      });
    }
  });

  // 2. Process standalone photos (album_id is NULL)
  const standalone = galleries.value.filter(g => !g.album_id);
  standalone.forEach(photo => {
    groups.push({
      id: `photo-${photo.id}`,
      name: photo.title || 'Wedding Memory',
      count: 1,
      image: photo.image,
      photos: [photo],
      uploadedBy: photo.user?.name || (photo.user_id === 1 ? 'Admin' : 'Guest'),
      user_id: photo.user_id,
      isAlbum: false
    });
  });

  return groups;
});

// Admin Filtered Albums
const myAlbums = computed(() => {
  if (authUser.value?.role === 'admin') return albums.value;
  return albums.value.filter(a => a.user_id === authUser.value?.id);
});
const myTimelinePhotos = computed(() => {
  const standalone = galleries.value.filter(g => !g.album_id);
  if (authUser.value?.role === 'admin') return standalone;
  return standalone.filter(g => g.user_id === authUser.value?.id);
});
const openAlbumGallery = (group) => {
  const $ = window.jQuery || window.$;
  if (!$ || !$.fn || !$.fn.magnificPopup) return;

  // Build a flat list of ALL photos across ALL albums, ordered starting from the clicked album
  const allGroups = groupedGallery.value;
  const clickedIndex = allGroups.findIndex(g => g.id === group.id);
  
  // Reorder: clicked album first, then the rest in order
  const orderedGroups = [
    ...allGroups.slice(clickedIndex),
    ...allGroups.slice(0, clickedIndex)
  ];

  // Build flat items list with album name in title
  const items = [];
  orderedGroups.forEach(g => {
    g.photos.forEach((p, photoIdx) => {
      items.push({
        src: p.image,
        title: `<span style="font-family: 'Sacramento', cursive; font-size: 28px; color: #F14E95;">${g.name}</span><br><span style="font-size: 13px; color: rgba(255,255,255,0.7);">${photoIdx + 1} of ${g.photos.length}</span>`,
        type: 'image'
      });
    });
  });

  // Use static open method to avoid 'e.is is not a function' error
  $.magnificPopup.open({
    items: items,
    type: 'image',
    gallery: {
      enabled: true,
      tCounter: '<span class="mfp-counter">%curr% of %total%</span>'
    },
    mainClass: 'mfp-fade',
    removalDelay: 300,
    closeBtnInside: true,
    image: {
      titleSrc: function(item) {
        return item.title;
      }
    }
  });
};
</script>

<template>
  <!-- MAIN WEBSITE CONTENT -->
  <div id="page" v-show="!showAdminPanel">
    <nav class="fh5co-nav" role="navigation">
      <div class="container">
        <div class="row">
          <div class="col-xs-2">
            <div id="fh5co-logo"><a href="#" @click.prevent="navigatePage('home')">Wedding<strong>.</strong></a></div>
          </div>
          <div class="col-xs-10 text-right menu-1">
            <ul>
              <li :class="{ active: pageTab === 'home' }"><a href="#" @click.prevent="navigatePage('home')">Home</a></li>
              <li v-if="pageTab === 'home'"><a href="#" @click.prevent="scrollToSection('fh5co-couple-story')">Story</a></li>
              <li v-if="pageTab === 'home'"><a href="#" @click.prevent="scrollToSection('fh5co-event')">Events</a></li>
              <li :class="{ active: pageTab === 'gallery' }"><a href="#" @click.prevent="navigatePage('gallery')">Gallery</a></li>
              <li v-if="pageTab === 'home'"><a href="#" @click.prevent="scrollToSection('fh5co-testimonial')">Wishes</a></li>
              <li v-if="pageTab === 'home'"><a href="#" @click.prevent="scrollToSection('fh5co-started')">RSVP</a></li>
              <li v-if="!authUser" class="btn-cta"><a href="#" @click.prevent="showLoginPanel = true"><span>Login</span></a></li>
              <li v-else class="btn-cta"><a href="#" @click.prevent="showAdminPanel = true"><span>Dashboard</span></a></li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <header id="fh5co-header" class="fh5co-cover" role="banner" style="background-image:url(/images/img_bg_2.jpg); background-position: 70% 0% !important; background-size: cover !important; background-attachment: fixed; width: 100%; border: none;" v-show="pageTab === 'home'">
      <div class="overlay"></div>
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 text-center">
            <div class="display-t">
              <div class="display-tc animate-box" data-animate-effect="fadeIn">
                <h1>Emanuel &amp; Jessalyn</h1>
                <h2 v-if="new Date() < new Date(2026, 2, 30, 8, 0, 0)">We Are Getting Married</h2>
                <h2 v-else>We Are Happily Married</h2>
                <div class="simply-countdown simply-countdown-one"></div>
                <p><a href="#" class="btn btn-default btn-sm">Save the date</a></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <header id="fh5co-header-gallery" class="fh5co-cover fh5co-cover-sm" role="banner" style="background-image:url(/images/img_bg_1.jpg); background-position: center center; max-height: 400px; display: flex; align-items: center;" v-show="pageTab === 'gallery'">
		  <div class="overlay"></div>
		  <div class="fh5co-container" style="width: 100%;">
			  <div class="row">
				  <div class="col-md-8 col-md-offset-2 text-center">
					  <div class="display-t">
						  <div class="display-tc animate-box" data-animate-effect="fadeIn">
							  <h1 style="color: white; font-family: 'Sacramento', cursive; font-size: 60px;">Photo Gallery</h1>
							  <h2 style="color: rgba(255,255,255,0.7); font-size: 20px;">Beautiful collections of our favorite memories</h2>
						  </div>
					  </div>
				  </div>
			  </div>
		  </div>
	  </header>
    <header id="fh5co-header-wishes" class="fh5co-cover fh5co-cover-sm" role="banner" style="background-image:url(/images/img_bg_1.jpg); background-position: center center; max-height: 400px; display: flex; align-items: center;" v-show="pageTab === 'wishes'">
		  <div class="overlay"></div>
		  <div class="fh5co-container" style="width: 100%;">
			  <div class="row">
				  <div class="col-md-8 col-md-offset-2 text-center">
					  <div class="display-t">
						  <div class="display-tc">
							  <h1 style="color: white; font-family: 'Sacramento', cursive; font-size: 60px;">Our Wishes</h1>
							  <h2 style="color: rgba(255,255,255,0.7); font-size: 20px; font-family: 'Work Sans', sans-serif;">Beautiful collections of messages from our loved ones</h2>
						  </div>
					  </div>
				  </div>
			  </div>
		  </div>
	  </header>

    <div id="fh5co-couple" v-show="pageTab === 'home'">
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 text-center fh5co-heading animate-box">
            <h2>Hello!</h2>
            <h3>March 30th, 2026 Linamon Municipal Hall, Lanao del Norte, Philippines</h3>
            <p>We invited you to celebrate our wedding</p>
          </div>
        </div>
        <div class="couple-wrap animate-box">
          <div class="couple-half">
            <div class="groom">
              <img src="/images/groom.jpg" alt="groom" class="img-responsive">
            </div>
            <div class="desc-groom">
              <h3>Emanuel Monsanto</h3>
              <p>Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove</p>
            </div>
          </div>
          <p class="heart text-center"><i class="icon-heart2"></i></p>
          <div class="couple-half">
            <div class="bride">
              <img src="/images/bride.jpg" alt="groom" class="img-responsive">
            </div>
            <div class="desc-bride">
              <h3>Jessalyn Emam</h3>
              <p>Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="fh5co-event" class="fh5co-bg" style="background-image:url(/images/img_bg_3.jpg);" v-show="pageTab === 'home'">
      <div class="overlay"></div>
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 text-center fh5co-heading animate-box">
            <span>Our Special Events</span>
            <h2>Wedding Events</h2>
          </div>
        </div>
        <div class="row">
          <div class="display-t">
            <div class="display-tc">
              <div class="col-md-10 col-md-offset-1">
                <div class="col-md-6 col-sm-6 text-center">
                  <div class="event-wrap animate-box">
                    <h3>Main Ceremony</h3>
                    <div class="event-col">
                      <i class="icon-clock"></i>
                      <span>4:00 PM</span>
                      <span>6:00 PM</span>
                    </div>
                    <div class="event-col">
                      <i class="icon-calendar"></i>
                      <span>Monday 28</span>
                      <span>November, 2016</span>
                    </div>
                    <p>Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean.</p>
                  </div>
                </div>
                <div class="col-md-6 col-sm-6 text-center">
                  <div class="event-wrap animate-box">
                    <h3>Wedding Party</h3>
                    <div class="event-col">
                      <i class="icon-clock"></i>
                      <span>7:00 PM</span>
                      <span>12:00 AM</span>
                    </div>
                    <div class="event-col">
                      <i class="icon-calendar"></i>
                      <span>Monday 28</span>
                      <span>November, 2016</span>
                    </div>
                    <p>Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="fh5co-couple-story" v-show="pageTab === 'home'">
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 text-center fh5co-heading animate-box">
            <span>We Love Each Other</span>
            <h2>Our Story</h2>
            <p>Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts.</p>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12 col-md-offset-0">
            <ul class="timeline animate-box">
              <li class="animate-box">
                <div class="timeline-badge" style="background-image:url(/images/couple-1.jpg);"></div>
                <div class="timeline-panel">
                  <div class="timeline-heading">
                    <h3 class="timeline-title">First We Meet</h3>
                    <span class="date">December 25, 2015</span>
                  </div>
                  <div class="timeline-body">
                    <p>Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean.</p>
                  </div>
                </div>
              </li>
              <li class="timeline-inverted animate-box">
                <div class="timeline-badge" style="background-image:url(/images/couple-2.jpg);"></div>
                <div class="timeline-panel">
                  <div class="timeline-heading">
                    <h3 class="timeline-title">First Date</h3>
                    <span class="date">December 28, 2015</span>
                  </div>
                  <div class="timeline-body">
                    <p>Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean.</p>
                  </div>
                </div>
              </li>
              <li class="animate-box">
                <div class="timeline-badge" style="background-image:url(/images/couple-3.jpg);"></div>
                <div class="timeline-panel">
                  <div class="timeline-heading">
                    <h3 class="timeline-title">In A Relationship</h3>
                    <span class="date">January 1, 2016</span>
                  </div>
                  <div class="timeline-body">
                    <p>Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean.</p>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div id="fh5co-gallery" class="fh5co-section-gray" v-show="pageTab === 'home' || pageTab === 'gallery'">
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 text-center fh5co-heading" :class="{ 'animate-box': pageTab === 'home' }">
            <span>Our Memories</span>
            <h2>Wedding Gallery</h2>
            <p>Every picture tells a beautiful story of our journey together.</p>
          </div>
        </div>
        <div class="row row-bottom-padded-md">
          <div class="col-md-12">
            <ul id="fh5co-gallery-list">
              <li v-for="group in groupedGallery" :key="group.id" class="one-third" :style="`background-image: url(${group.image}); opacity: 1; margin-bottom: 25px; border-radius: 8px;` "> 
                <a href="#" @click.prevent="openAlbumGallery(group)" class="album-trigger">
                  <div class="case-studies-summary">
                    <span>{{ group.count }} Photos</span>
                    <h2>{{ group.name }}</h2>
                  </div>
                </a>
                <!-- Hidden inputs for Magnific Popup to find all photos in the group -->
                <div class="hidden-gallery" style="display:none">
                  <a v-for="p in group.photos" :key="p.id" :href="p.image" class="image-popup-item" :data-album="group.id"></a>
                </div>
              </li>
            </ul>		
          </div>
        </div>
      </div>
    </div>

    <div id="fh5co-counter" class="fh5co-bg fh5co-counter" style="background-image:url(/images/img_bg_5.jpg);" v-show="pageTab === 'home'">
      <div class="overlay"></div>
      <div class="container">
        <div class="row">
          <div class="display-t">
            <div class="display-tc">
              <div class="col-md-3 col-sm-6 animate-box">
                <div class="feature-center">
                  <span class="icon"><i class="icon-users"></i></span>
                  <span class="counter js-counter" data-from="0" data-to="500" data-speed="5000" data-refresh-interval="50">1</span>
                  <span class="counter-label">Estimated Guest</span>
                </div>
              </div>
              <div class="col-md-3 col-sm-6 animate-box">
                <div class="feature-center">
                  <span class="icon"><i class="icon-user"></i></span>
                  <span class="counter js-counter" data-from="0" data-to="1000" data-speed="5000" data-refresh-interval="50">1</span>
                  <span class="counter-label">We Catter</span>
                </div>
              </div>
              <div class="col-md-3 col-sm-6 animate-box">
                <div class="feature-center">
                  <span class="icon"><i class="icon-calendar"></i></span>
                  <span class="counter js-counter" data-from="0" data-to="402" data-speed="5000" data-refresh-interval="50">1</span>
                  <span class="counter-label">Events Done</span>
                </div>
              </div>
              <div class="col-md-3 col-sm-6 animate-box">
                <div class="feature-center">
                  <span class="icon"><i class="icon-clock"></i></span>
                  <span class="counter js-counter" data-from="0" data-to="2345" data-speed="5000" data-refresh-interval="50">1</span>
                  <span class="counter-label">Hours Spent</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="fh5co-testimonial" v-show="pageTab === 'home'">
      <div class="container">
        <div class="row">
          <div class="row animate-box">
            <div class="col-md-8 col-md-offset-2 text-center fh5co-heading">
              <span>Best Wishes</span>
              <h2>Friends Wishes</h2>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12 animate-box">
              <div class="wrap-testimony">
                <div class="owl-carousel-fullwidth" :key="carouselKey">
                  <div v-for="wish in wishes" :key="wish.id" class="item">
                    <div class="testimony-slide active text-center">
                      <figure>
                        <img :src="wish.image ? wish.image : '/images/couple-1.jpg'" alt="user">
                      </figure>
                      <span>{{ wish.name }}, via <span style="color: #F14E95;">{{ wish.category ? wish.category.toUpperCase() : 'GUEST' }}</span></span>
                      <div style="font-size: 11px; color: #999; margin-top: 2px;">{{ formatDate(wish.created_at) }}</div>
                      
                      <!-- ADMIN MODERATION BUTTONS (Carousel) -->
                      <div v-if="authUser && authUser.role === 'admin'" style="display: inline-flex; gap: 10px; margin-left: 10px; align-items: center;">
                        <button @click.stop="toggleWishVisibility(wish.id)" style="background: none; border: none; color: #828282; cursor: pointer; font-size: 16px;" :title="wish.is_hidden ? 'Show Wish' : 'Hide Wish'">
                          <i :class="wish.is_hidden ? 'icon-eye-off' : 'icon-eye'"></i>
                        </button>
                        <button @click.stop="deleteWish(wish.id)" style="background: none; border: none; color: #ff3c3c; cursor: pointer; font-size: 14px;" title="Delete Wish">
                          <i class="icon-trash"></i>
                        </button>
                      </div>

                      <blockquote :style="{ opacity: wish.is_hidden ? '0.5' : '1' }">
                        <p>"{{ wish.expanded || wish.message.length <= 220 ? wish.message : wish.message.substring(0, 220) + '...' }}"
                          <a v-if="!wish.expanded && wish.message.length > 220" href="#" @click.prevent="wish.expanded = true" style="color: #F14E95; text-decoration: none; font-weight: bold;">see all</a>
                          <a v-if="wish.expanded && wish.message.length > 220" href="#" @click.prevent="wish.expanded = false" style="color: #F14E95; text-decoration: none; font-weight: bold;">see less</a>
                        </p>
                      </blockquote>
                    </div>
                  </div>
                  <div v-if="!wishes.length" class="item">
                    <div class="testimony-slide active text-center">
                      <figure>
                        <img src="/images/couple-1.jpg" alt="user">
                      </figure>
                      <span>GUEST NAME, via <span style="color: #F14E95;">FAMILY</span></span>
                      <blockquote>
                        <p>"Best wishes to the beautiful couple on their special day!"</p>
                      </blockquote>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- View All Link (Added) -->
          <div class="row">
            <div class="col-md-12 text-right" style="margin-top: 20px;">
              <a href="#" @click.prevent="navigatePage('wishes')" style="color: #F14E95; font-family: 'Work Sans', sans-serif; font-weight: bold; border-bottom: 2px solid #F14E95; padding-bottom: 5px; text-decoration: none; font-size: 16px; transition: 0.3s;" onmouseover="this.style.color='#d83b7f'; this.style.borderColor='#d83b7f'" onmouseout="this.style.color='#F14E95'; this.style.borderColor='#F14E95'">
                VIEW ALL WISHES &rarr;
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="fh5co-services" class="fh5co-section-gray" v-show="pageTab === 'home'">
      <div class="container">
        <div class="row animate-box">
          <div class="col-md-8 col-md-offset-2 text-center fh5co-heading">
            <h2>We Offer Services</h2>
            <p>Dignissimos asperiores vitae velit veniam totam fuga molestias accusamus alias autem provident. Odit ab aliquam dolor eius.</p>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="feature-left animate-box" data-animate-effect="fadeInLeft">
              <span class="icon">
                <i class="icon-calendar"></i>
              </span>
              <div class="feature-copy">
                <h3>We Organized Events</h3>
                <p>Facilis ipsum reprehenderit nemo molestias. Aut cum mollitia reprehenderit. Eos cumque dicta adipisci architecto culpa amet.</p>
              </div>
            </div>
            <div class="feature-left animate-box" data-animate-effect="fadeInLeft">
              <span class="icon">
                <i class="icon-image"></i>
              </span>
              <div class="feature-copy">
                <h3>Photoshoot</h3>
                <p>Facilis ipsum reprehenderit nemo molestias. Aut cum mollitia reprehenderit. Eos cumque dicta adipisci architecto culpa amet.</p>
              </div>
            </div>
            <div class="feature-left animate-box" data-animate-effect="fadeInLeft">
              <span class="icon">
                <i class="icon-video"></i>
              </span>
              <div class="feature-copy">
                <h3>Video Editing</h3>
                <p>Facilis ipsum reprehenderit nemo molestias. Aut cum mollitia reprehenderit. Eos cumque dicta adipisci architecto culpa amet.</p>
              </div>
            </div>
          </div>
          <div class="col-md-6 animate-box">
            <div class="fh5co-video fh5co-bg" style="background-image: url(/images/img_bg_3.jpg); ">
              <a href="https://vimeo.com/channels/staffpicks/93951774" class="popup-vimeo"><i class="icon-video2"></i></a>
              <div class="overlay"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="fh5co-started" class="fh5co-bg" style="background-image:url(/images/img_bg_4.jpg); padding: 7em 0;" v-show="pageTab === 'home'">
      <div class="overlay"></div>
      <div class="container">
        <div class="row animate-box">
          <div class="col-md-8 col-md-offset-2 text-center fh5co-heading">
            <h2>Are You Attending?</h2>
            <p>Please Fill-up the form to notify you that you're attending. Thanks.</p>
          </div>
        </div>
        <div class="row animate-box">
          <div class="col-md-10 col-md-offset-1">
            <div style="background: rgba(255,255,255,0.1); padding: 40px; border-radius: 15px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
              <form @submit.prevent="submitRSVP" style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Avatar Upload (Restored) -->
                <div style="display: flex; justify-content: center; margin-bottom: 10px;">
                  <label style="cursor: pointer; position: relative; width: 100px; height: 100px; border-radius: 50%; background-color: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; overflow: visible; border: 2px solid rgba(255,255,255,0.5);">
                    <div v-if="!rsvpForm.image" style="color: white; display: flex; flex-direction: column; align-items: center;">
                      <svg width="50" height="50" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12ZM12 14C9.33 14 4 15.34 4 18V20H20V18C20 15.34 14.67 14 12 14Z" />
                      </svg>
                    </div>
                    <img v-if="rsvpForm.image" :src="rsvpForm.image" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <div style="position: absolute; bottom: 0; right: 0; background: #F14E95; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                      <i class="icon-camera" style="color: white; font-size: 14px;"></i>
                    </div>
                    <input type="file" @change="handleImageUpload" accept="image/*" style="display: none;">
                  </label>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <input v-model="rsvpForm.name" type="text" class="form-control" placeholder="Full Name" required style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); color: white; height: 50px;">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <select v-model="rsvpForm.category" class="form-control" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); color: white; height: 50px;">
                        <option value="Family" style="color: black;">Family</option>
                        <option value="Friends" style="color: black;">Friends</option>
                        <option value="Guest" style="color: black;">Guest</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <textarea v-model="rsvpForm.message" class="form-control" placeholder="Your Message" style="width: 100%; min-height: 120px; padding: 15px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: white; resize: none;" required></textarea>
                </div>

                <div class="text-center">
                  <button type="submit" 
                          class="btn btn-primary" 
                          :style="{ 
                            background: '#ff3c3c', 
                            border: 'none', 
                            fontWeight: 'bold', 
                            borderRadius: '30px', 
                            padding: '15px 60px', 
                            fontSize: '16px', 
                            textTransform: 'uppercase', 
                            letterSpacing: '1px', 
                            boxShadow: '0 4px 15px rgba(255, 60, 60, 0.3)',
                            opacity: (!rsvpForm.name || !rsvpForm.message || !rsvpForm.image) ? '0.6' : '1',
                            cursor: (!rsvpForm.name || !rsvpForm.message || !rsvpForm.image) ? 'not-allowed' : 'pointer'
                          }">
                    CONFIRM NOW
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ALL WISHES PAGE (New) -->
    <div id="fh5co-all-wishes" v-show="pageTab === 'wishes'" style="padding: 100px 0; background: #fdf5eb; min-height: 100vh;">
      <div class="container">
        <div class="row">
          <div class="col-md-8 col-md-offset-2 text-center fh5co-heading">
            <span style="font-family: 'Work Sans', Arial, sans-serif; text-transform: uppercase; letter-spacing: 2px; color: #828282; font-size: 14px; font-weight: 600;">Full Collection</span>
            <h2 style="font-family: 'Sacramento', Arial, serif; font-size: 60px; color: #F14E95; margin-bottom: 10px;">All Best Wishes</h2>
            <p>Every message from our loved ones means the world to us.</p>
          </div>
        </div>

        <div class="row" style="display: flex; flex-wrap: wrap;">
          <div v-for="wish in wishes" :key="wish.id" class="col-md-4 col-sm-6" style="margin-bottom: 30px; display: flex;">
            <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); height: 100%; width: 100%; display: flex; flex-direction: column; align-items: center; text-align: center; border: 1px solid #f1e1cb; transition: 0.3s; position: relative;" :style="{ opacity: wish.is_hidden ? '0.6' : '1' }" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 30px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(0,0,0,0.05)'">
              
              <!-- ADMIN MODERATION BUTTONS (Grid) -->
              <div v-if="authUser && authUser.role === 'admin'" style="position: absolute; top: 15px; right: 15px; display: flex; gap: 8px;">
                <button @click.stop="toggleWishVisibility(wish.id)" style="background: rgba(130, 130, 130, 0.1); border: none; color: #828282; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='rgba(130, 130, 130, 0.2)'" onmouseout="this.style.background='rgba(130, 130, 130, 0.1)'" :title="wish.is_hidden ? 'Show Wish' : 'Hide Wish'">
                  <i :class="wish.is_hidden ? 'icon-eye-off' : 'icon-eye'"></i>
                </button>
                <button @click.stop="deleteWish(wish.id)" style="background: rgba(255, 60, 60, 0.1); border: none; color: #ff3c3c; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='rgba(255, 60, 60, 0.2)'" onmouseout="this.style.background='rgba(255, 60, 60, 0.1)'" title="Delete Wish">
                  <i class="icon-trash"></i>
                </button>
              </div>

              <figure style="margin-bottom: 20px;">
                <img :src="wish.image ? wish.image : '/images/couple-1.jpg'" alt="user" style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 3px solid #F14E95; padding: 3px; background: white;">
              </figure>
              <h3 style="margin-bottom: 5px; font-family: 'Sacramento', cursive; font-size: 32px; color: #F14E95;">{{ wish.name }}</h3>
              <span style="display: block; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; color: #828282; margin-bottom: 5px;">Via {{ wish.category || 'Guest' }}</span>
              <div style="font-size: 11px; color: #bbb; margin-bottom: 15px;">{{ formatDate(wish.created_at) }}</div>
              
              <blockquote style="padding: 0; border: none; font-style: italic; color: #666; flex-grow: 1; display: flex; align-items: center; justify-content: center;">
                <p style="font-family: 'Work Sans', sans-serif; font-style: italic; color: #666; line-height: 1.8; font-size: 15px; margin: 0;">"{{ wish.message }}"</p>
              </blockquote>
            </div>
          </div>
          
          <div v-if="!wishes.length" class="col-md-12 text-center" style="padding: 100px 0;">
            <i class="icon-heart" style="font-size: 60px; color: #F14E95; opacity: 0.2; display: block; margin-bottom: 20px;"></i>
            <p style="font-size: 20px; color: #999; font-family: 'Work Sans', sans-serif;">Wait, no wishes yet! Be the first to leave a message below.</p>
          </div>
        </div>

        <div class="row text-center" style="margin-top: 60px;">
          <button @click.prevent="navigatePage('home')" class="btn btn-primary" style="background: #F14E95; border: none; border-radius: 30px; padding: 15px 40px; font-weight: bold; letter-spacing: 1px; box-shadow: 0 4px 15px rgba(241, 78, 149, 0.3);">BACK TO HOME</button>
        </div>
      </div>
    </div>

    <footer id="fh5co-footer" role="contentinfo">
      <div class="container">
        <div class="row copyright">
          <div class="col-md-12 text-center">
            <p>
              <small class="block">&copy; 2016 Free HTML5. All Rights Reserved.</small> 
              <small class="block">Designed by <a href="https://www.facebook.com/Angelow143" target="_blank">Angelow</a> Demo Images: <a href="https://www.facebook.com/Angelow143" target="_blank">Angelow</a></small>
            </p>
            <p>
              <ul class="fh5co-social-icons">
                <li><a href="#"><i class="icon-twitter"></i></a></li>
                <li><a href="#"><i class="icon-facebook"></i></a></li>
                <li><a href="#"><i class="icon-linkedin"></i></a></li>
                <li><a href="#"><i class="icon-dribbble"></i></a></li>
              </ul>
            </p>
          </div>
        </div>
      </div>
    </footer>
  </div>

    <!-- AUTH MODAL (Login / Register) -->
    <div v-if="showLoginPanel && !showAdminPanel" style="position: fixed; z-index: 100000; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center;">
      <div style="background: white; padding: 40px; border-radius: 12px; width: 450px; max-width: 90%; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.3);">
        <button @click.prevent="showLoginPanel = false; showRegisterPanel = false" style="position: absolute; top: 15px; right: 20px; border: none; background: transparent; font-size: 24px; font-weight: bold; cursor: pointer; color: #999; transition: 0.2s;" onmouseover="this.style.color='#F14E95'" onmouseout="this.style.color='#999'">&times;</button>
        
        <h3 style="text-align: center; color: #F14E95; font-family: 'Sacramento', cursive; font-size: 36px; margin-bottom: 30px;">
          {{ showRegisterPanel ? 'Register New User' : 'Authorized Login' }}
        </h3>

        <!-- LOGIN FORM -->
        <form v-if="!showRegisterPanel" @submit.prevent="handleLogin">
          <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Email Address</label>
            <input v-model="loginForm.email" type="email" placeholder="Email" class="form-control" required style="width: 100%; height: 45px; border: 1px solid #dfe6e9; padding: 0 15px; border-radius: 6px; outline: none; background: #fafbfc; font-size: 15px;" />
          </div>
          
          <div style="margin-bottom: 25px;">
            <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Password</label>
            <div style="position: relative;">
              <input v-model="loginForm.password" :type="showLoginPassword ? 'text' : 'password'" placeholder="Password" class="form-control" required style="width: 100%; height: 45px; border: 1px solid #dfe6e9; padding: 0 15px; padding-right: 45px; border-radius: 6px; outline: none; background: #fafbfc; font-size: 15px;" />
              <button type="button" @click="showLoginPassword = !showLoginPassword" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: none; background: transparent; cursor: pointer; color: #888; font-size: 18px;">
                <span v-if="!showLoginPassword">👁️</span>
                <span v-else>🙈</span>
              </button>
            </div>
          </div>
          
          <button type="submit" style="background: #F14E95; color: white; border: none; width: 100%; padding: 14px; border-radius: 6px; font-weight: 600; font-size: 16px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s; margin-bottom: 20px;" onmouseover="this.style.background='#d83b7f'" onmouseout="this.style.background='#F14E95'">LOGIN</button>
          
          <p style="text-align: center; color: #777; font-size: 14px;">
            Don't have an account? <a href="#" @click.prevent="showRegisterPanel = true" style="color: #F14E95; font-weight: bold; text-decoration: none;">Register here</a>
          </p>
        </form>

        <!-- REGISTER FORM -->
        <form v-else @submit.prevent="handleRegister">
          <p style="font-size: 13px; color: #777; margin-bottom: 25px; text-align: center;">Create standard user accounts to allow family members or photographers to upload to the gallery.</p>
          
          <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Full Name</label>
            <input v-model="registerForm.name" @input="autoGenerateEmail" type="text" placeholder="John Doe" required style="width: 100%; height: 45px; border: 1px solid #dfe6e9; padding: 0 15px; border-radius: 6px; outline: none; background: #fafbfc; font-size: 15px;" />
          </div>

          <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Email Address (Generated)</label>
            <div style="display: flex; gap: 8px;">
              <input v-model="registerForm.email" type="text" readonly style="flex: 1; height: 45px; border: 1px solid #dfe6e9; padding: 0 15px; border-radius: 6px; outline: none; background: #eee; font-size: 15px; color: #555; cursor: not-allowed;" />
              <button type="button" @click="generateRandomEmail" style="background: #27ae60; color: white; border: none; padding: 0 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; white-space: nowrap;">♻ Generate Again</button>
            </div>
            <small style="color: #27ae60; font-size: 11px;">* All accounts use @wedding.ph domain.</small>
          </div>

          <div style="margin-bottom: 25px;">
            <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Setup Password</label>
            <div style="position: relative;">
              <input v-model="registerForm.password" :type="showRegisterPassword ? 'text' : 'password'" placeholder="Minimum 6 characters" required style="width: 100%; height: 45px; border: 1px solid #dfe6e9; padding: 0 15px; padding-right: 45px; border-radius: 6px; outline: none; background: #fafbfc; font-size: 15px;" />
              <button type="button" @click="showRegisterPassword = !showRegisterPassword" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: none; background: transparent; cursor: pointer; color: #888; font-size: 18px;">
                <span v-if="!showRegisterPassword">👁️</span>
                <span v-else>🙈</span>
              </button>
            </div>
          </div>
          
          <button type="submit" style="background: #27ae60; color: white; border: none; width: 100%; padding: 14px; border-radius: 6px; font-weight: 600; font-size: 16px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s; margin-bottom: 20px;" onmouseover="this.style.background='#219150'" onmouseout="this.style.background='#27ae60'">CREATE USER ACCOUNT</button>
          
          <p style="text-align: center; color: #777; font-size: 14px;">
            Already have an account? <a href="#" @click.prevent="showRegisterPanel = false" style="color: #F14E95; font-weight: bold; text-decoration: none;">Login here</a>
          </p>
        </form>
      </div>
    </div>

  <!-- FULL SCREEN ADMIN DASHBOARD -->
    <div v-if="showAdminPanel" style="height: 100vh; overflow: hidden; background: #f0f2f5; display: flex; font-family: 'Work Sans', sans-serif; position: fixed; top: 0; left: 0; width: 100vw; z-index: 99999;">
      
      <!-- Sidebar / Menu bar -->
      <aside style="width: 260px; background: #2c3e50; color: white; display: flex; flex-direction: column; padding-top: 30px; box-shadow: 2px 0 10px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 40px; position: relative;">
          <h2 style="color: #F14E95; font-family: 'Sacramento', cursive; font-size: 40px; margin: 0 0 15px;">Wedding.</h2>
          <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
            <img v-if="authUser?.image" :src="authUser.image" alt="Profile" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid #34495e; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <span v-if="authUser?.name" style="font-size: 14px; color: white; font-weight: bold;">{{ authUser.name }}</span>
            <span v-if="authUser?.role === 'admin'" style="font-size: 10px; color: #F14E95; letter-spacing: 2px;">SUPERUSER</span>
          </div>
        </div>

        <ul style="list-style: none; padding: 0; margin: 0; flex-grow: 1;">
          <li>
            <a href="#" @click.prevent="adminTab = 'gallery'" 
               :style="{ display: 'block', padding: '15px 30px', color: adminTab === 'gallery' ? 'white' : '#a1b5c9', background: adminTab === 'gallery' ? '#34495e' : 'transparent', textDecoration: 'none', borderLeft: adminTab === 'gallery' ? '4px solid #F14E95' : '4px solid transparent', transition: '0.2s' }">
              Photo Gallery
            </a>
          </li>
          <li v-if="authUser?.role === 'admin'">
            <a href="#" @click.prevent="adminTab = 'users'; fetchUsersList()"
               :style="{ display: 'block', padding: '15px 30px', color: adminTab === 'users' ? 'white' : '#a1b5c9', background: adminTab === 'users' ? '#34495e' : 'transparent', textDecoration: 'none', borderLeft: adminTab === 'users' ? '4px solid #F14E95' : '4px solid transparent', transition: '0.2s' }">
              Manage Users
            </a>
          </li>
          <li v-if="authUser?.role === 'admin'">
            <a href="#" @click.prevent="adminTab = 'settings'; fetchSettings()"
               :style="{ display: 'block', padding: '15px 30px', color: adminTab === 'settings' ? 'white' : '#a1b5c9', background: adminTab === 'settings' ? '#34495e' : 'transparent', textDecoration: 'none', borderLeft: adminTab === 'settings' ? '4px solid #F14E95' : '4px solid transparent', transition: '0.2s' }">
              System Settings
            </a>
          </li>
          <li>
            <a href="#" @click.prevent="openEditProfile" 
               :style="{ display: 'block', padding: '15px 30px', color: adminTab === 'profile' ? 'white' : '#a1b5c9', background: adminTab === 'profile' ? '#34495e' : 'transparent', textDecoration: 'none', borderLeft: adminTab === 'profile' ? '4px solid #F14E95' : '4px solid transparent', transition: '0.2s' }">
              Edit Profile
            </a>
          </li>
        </ul>

        <div style="padding: 20px; background: #243342; text-align: center;">
          <p style="font-size: 13px; color: #7f8c8d; margin-bottom: 10px;">Logged in as <b>{{ authUser?.name }}</b></p>
          <button @click.prevent="handleLogout" style="width: 100%; background: transparent; border: 1px solid #e74c3c; color: #e74c3c; padding: 8px; border-radius: 4px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#e74c3c'; this.style.color='white'" onmouseout="this.style.background='transparent'; this.style.color='#e74c3c'">Sign Out</button>
        </div>
      </aside>

      <!-- Main Dashboard Content -->
      <main style="flex-grow: 1; padding: 50px; overflow-y: auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
          <h2 style="color: #2c3e50; font-size: 28px; font-weight: 600; margin: 0;">
            {{ adminTab === 'gallery' ? 'Upload Gallery Photo' : (adminTab === 'users' ? 'System Users' : (adminTab === 'settings' ? 'System Settings' : 'Edit Profile')) }}
          </h2>
          <button @click.prevent="showAdminPanel = false" style="background: white; border: 1px solid #ddd; padding: 10px 20px; border-radius: 20px; color: #555; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <span>&larr; Back to Website</span>
          </button>
        </div>
        
        <!-- Gallery & Albums Tab -->
        <div v-if="adminTab === 'gallery'">
          
          <!-- NAVIGATION & HEADER -->
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 20px;">
             <div>
                <h4 style="margin: 0; color: #F14E95; font-size: 24px; font-family: 'Work Sans', sans-serif;">
                  {{ currentAlbum ? currentAlbum.name : 'Photo Albums' }}
                </h4>
                <p style="color: #777; margin: 5px 0 0; font-size: 14px;">
                  {{ currentAlbum ? 'Upload and manage memories in this album.' : 'Organize your wedding memories into beautiful collections.' }}
                </p>
             </div>
             
             <div style="display: flex; gap: 10px;">
               <button v-if="currentAlbum" @click.prevent="currentAlbum = null" style="background: #ecf0f1; border: none; color: #555; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.2s;">
                 &larr; Back to Albums
               </button>
               <button v-if="!currentAlbum" @click.prevent="showCreateAlbumForm = !showCreateAlbumForm" style="background: #F14E95; border: none; color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#d83b7f'" onmouseout="this.style.background='#F14E95'">
                 {{ showCreateAlbumForm ? '✖ Cancel' : '✚ Create New Album' }}
               </button>
             </div>
          </div>

          <!-- CREATE ALBUM FORM -->
          <div v-if="showCreateAlbumForm && !currentAlbum" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: 1px solid #eee; margin-bottom: 30px;">
             <form @submit.prevent="handleCreateAlbum" style="display: flex; gap: 15px; align-items: flex-end;">
               <div style="flex: 1;">
                 <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Album Name</label>
                 <input v-model="albumForm.name" type="text" placeholder="e.g. Preparation, Reception, Honeymoon" required style="border: 1px solid #dfe6e9; height: 45px; width: 100%; padding: 0 15px; border-radius: 6px; outline: none; background: #fafbfc;" />
               </div>
               <button type="submit" :disabled="isCreatingAlbum" style="background: #27ae60; color: white; border: none; padding: 0 30px; height: 45px; border-radius: 6px; font-weight: bold; cursor: pointer;" :style="{ opacity: isCreatingAlbum ? '0.7' : '1', cursor: isCreatingAlbum ? 'not-allowed' : 'pointer' }">{{ isCreatingAlbum ? 'CREATING...' : 'CREATE ALBUM' }}</button>
             </form>
          </div>

          <!-- ALBUM GRID (Selection View) -->
          <div v-if="!currentAlbum" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 25px;">
             
             <!-- Special Virtual Album: Timeline / Standalone Photos -->
             <div v-if="myTimelinePhotos.length > 0" @click="selectAlbum({ id: 'timeline', name: 'Timeline Photos', photos: myTimelinePhotos })" style="cursor: pointer; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 2px solid #3498db; transition: 0.3s; position: relative;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.05)'">
                <div style="height: 180px; background: #ebf5fb; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
                   <img :src="myTimelinePhotos[0].image" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;">
                   <div style="position: absolute; inset: 0; background: rgba(52, 152, 219, 0.2); display: flex; align-items: center; justify-content: center;">
                      <i class="icon-camera" style="font-size: 40px; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"></i>
                   </div>
                   <div style="position: absolute; bottom: 10px; right: 10px; background: #3498db; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold;">
                     {{ authUser?.name || 'Admin' }} {{ myTimelinePhotos.length }} Photos
                   </div>
                   <div style="position: absolute; top: 10px; left: 10px; background: #3498db; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase;">
                     Timeline
                   </div>
                </div>
                <div style="padding: 15px; text-align: center; position: relative;">
                   <h5 style="margin: 0; color: #3498db; font-weight: bold; font-size: 16px;">Timeline Photos</h5>
                </div>
             </div>

             <!-- Dynamic Albums -->
             <div v-for="album in myAlbums" :key="album.id" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #eee; transition: 0.3s; position: relative;">
                <div @click="selectAlbum(album)" style="cursor: pointer; height: 180px; background: #f9f9f9; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                   <img v-if="album.photos && album.photos.length" :src="album.photos[0].image" style="width: 100%; height: 100%; object-fit: cover;">
                   <div v-else style="color: #ccc; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                      <i class="icon-camera" style="font-size: 40px;"></i>
                      <span style="font-size: 12px;">Empty Album</span>
                   </div>
                   <div style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold;">
                     {{ album.user?.name || 'Admin' }} {{ album.photos ? album.photos.length : 0 }} Photos
                   </div>
                </div>
                <div style="padding: 15px; text-align: center;">
                   <h5 style="margin: 0; color: #2c3e50; font-weight: bold; font-size: 16px;">{{ album.name }}</h5>
                   
                   <!-- Edit Button -->
                   <button v-if="album.user_id === authUser?.id || authUser?.role === 'admin'" @click.stop="handleRenameAlbum(album)" style="position: absolute; top: 10px; right: 10px; background: white; border: 1px solid #ddd; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" title="Rename Album">✎</button>
                   
                   <!-- Delete Button -->
                   <button v-if="album.user_id === authUser?.id || authUser?.role === 'admin'" @click.stop="handleDeleteAlbum(album)" style="position: absolute; top: 10px; left: 10px; background: white; border: 1px solid #ff4d4d; color: #ff4d4d; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" title="Delete Album">🗑</button>
                </div>
             </div>
             
             <!-- Empty State -->
             <div v-if="!albums.length && !galleries.some(g => !g.album_id) && !showCreateAlbumForm" style="grid-column: 1 / -1; text-align: center; padding: 60px; background: #fafbfc; border: 2px dashed #eee; border-radius: 12px; color: #999;">
                <i class="icon-image" style="font-size: 60px; display: block; margin-bottom: 20px; color: #eee;"></i>
                <p style="font-size: 18px; margin-bottom: 20px;">No albums created yet.</p>
                <button @click.prevent="showCreateAlbumForm = true" style="background: #F14E95; color: white; border: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; cursor: pointer;">Start your first collection</button>
             </div>
          </div>

          <!-- ALBUM DETAIL VIEW (Upload & Photos) -->
          <div v-if="currentAlbum">
             <!-- Upload Form (Embedded in Album) -->
             <div v-if="currentAlbum.id !== 'timeline'" style="background: white; padding: 35px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eee; margin-bottom: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
                 <div>
                    <h5 style="margin: 0 0 15px; color: #F14E95; font-size: 18px; font-weight: bold;">Add New memory to "{{ currentAlbum.name }}"</h5>
                    <form @submit.prevent="handleAddGallery">
                      <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 13px;">Photo Title / Description</label>
                        <input v-model="galleryForm.title" type="text" placeholder="e.g. Hand in hand" style="border: 1px solid #dfe6e9; height: 40px; width: 100%; padding: 0 15px; border-radius: 6px; outline: none; background: #fafbfc;" />
                      </div>
                      <div style="margin-bottom: 20px;">
                         <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 13px;">Select Photo(s)</label>
                         <div style="border: 2px dashed #dfe6e9; border-radius: 8px; padding: 15px; text-align: center; background: #fafbfc; cursor: pointer;" onclick="this.querySelector('input').click()">
                            <input type="file" @change="handleGalleryUpload" accept="image/*" multiple required style="width: 100%; color: #555; display: none;" />
                            <div v-if="!galleryForm.image && (!galleryForm.images || !galleryForm.images.length)" style="color: #999; font-size: 13px;">
                               <i class="icon-camera" style="font-size: 24px; display: block; margin-bottom: 5px;"></i>
                               Click to Choose (Multiple Supported)
                            </div>
                            <div v-else style="color: #27ae60; font-size: 13px; font-weight: bold;">
                               <i class="icon-check" style="font-size: 18px;"></i>
                               <span v-if="galleryForm.images && galleryForm.images.length > 1">{{ galleryForm.images.length }} Images Selected</span>
                               <span v-else>Image Selected</span>
                            </div>
                         </div>
                      </div>
                      <button type="submit" :disabled="isUploadingPhoto" style="background: #F14E95; color: white; border: none; width: 100%; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; text-transform: uppercase;" :style="{ opacity: isUploadingPhoto ? '0.7' : '1', cursor: isUploadingPhoto ? 'not-allowed' : 'pointer' }">{{ isUploadingPhoto ? 'UPLOADING...' : 'Upload to Album' }}</button>
                    </form>
                 </div>
                 
                 <!-- Album Stats Box -->
                 <div style="background: #fafbfc; border-radius: 12px; padding: 25px; border: 1px solid #eee; text-align: center;">
                    <div style="font-size: 48px; color: #F14E95; font-weight: 800; line-height: 1;">{{ currentAlbum.photos ? currentAlbum.photos.length : 0 }}</div>
                    <div style="text-transform: uppercase; letter-spacing: 2px; font-size: 12px; color: #777; margin-top: 5px;">Total Photos</div>
                    <div v-if="currentAlbum.photos && currentAlbum.photos.length" style="margin-top: 20px; text-align: left; background: white; border-radius: 8px; padding: 10px; border: 1px solid #eee; overflow: hidden;">
                       <span style="font-size: 11px; color: #999; font-weight: bold; text-transform: uppercase; display: block; margin-bottom: 8px;">Album Cover View</span>
                       <img :src="currentAlbum.photos[0].image" style="width: 100%; border-radius: 4px; height: 120px; object-fit: cover;">
                    </div>
                 </div>
             </div>

             <!-- PHOTO GRID IN ALBUM -->
             <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                <div v-for="photo in (currentAlbum.photos || [])" :key="photo.id" style="position: relative; border-radius: 8px; overflow: hidden; height: 150px; background: #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                   <img :src="photo.image" style="width: 100%; height: 100%; object-fit: cover;">
                   <div v-if="photo.title" style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: white; font-size: 10px; padding: 5px; text-align: center;">
                      {{ photo.title }}
                   </div>
                   
                   <!-- Delete Photo Button -->
                   <button v-if="photo.user_id === authUser?.id || authUser?.role === 'admin'" @click.stop="handleDeletePhoto(photo)" style="position: absolute; top: 5px; right: 5px; background: rgba(255,255,255,0.8); border: none; color: #ff4d4d; width: 24px; height: 24px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" title="Delete Photo">🗑</button>
                </div>
                
                <div v-if="!currentAlbum.photos || !currentAlbum.photos.length" style="grid-column: 1 / -1; text-align: center; padding: 40px; border: 1px dashed #ccc; border-radius: 12px; color: #aaa;">
                    No photos in this album yet. Upload your first one!
                </div>
             </div>
          </div>

        </div>

        <!-- Manage Users Tab (Admin Only) -->
        <div v-if="adminTab === 'users' && authUser?.role === 'admin'">
          
          <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <button @click.prevent="showAddUserForm = !showAddUserForm" style="background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.2s;" onmouseover="this.style.background='#219653'" onmouseout="this.style.background='#27ae60'">
              <span style="font-size: 16px;">{{ showAddUserForm ? '✖' : '✚' }}</span> 
              {{ showAddUserForm ? 'Cancel' : 'Add New User' }}
            </button>
          </div>

          <div v-if="showAddUserForm" style="background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); margin-bottom: 40px;">
            <h4 style="margin-top: 0; color: #27ae60; font-size: 20px;">Register New User</h4>
            <p style="font-size: 14px; color: #777; margin-bottom: 25px;">Create standard user accounts to allow family members or photographers to upload to the gallery.</p>
            <form @submit.prevent="handleCreateUser">
              <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                  <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Full Name</label>
                  <input v-model="createUserForm.name" type="text" placeholder="John Doe" required style="border: 1px solid #dfe6e9; height: 45px; width: 100%; padding: 0 15px; border-radius: 6px; outline: none; background: #fafbfc;" />
                </div>
                <div style="flex: 1;">
                  <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Email Address</label>
                  <input v-model="createUserForm.email" type="email" placeholder="user@example.com" required style="border: 1px solid #dfe6e9; height: 45px; width: 100%; padding: 0 15px; border-radius: 6px; outline: none; background: #fafbfc;" />
                </div>
              </div>
              <div style="margin-bottom: 30px;">
                <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Setup Password</label>
                <input v-model="createUserForm.password" type="password" placeholder="Minimum 6 characters" required style="border: 1px solid #dfe6e9; height: 45px; width: 100%; padding: 0 15px; border-radius: 6px; outline: none; background: #fafbfc;" />
              </div>
              <button type="submit" style="background: #27ae60; color: white; border: none; padding: 14px 40px; border-radius: 6px; font-weight: 600; font-size: 16px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s;" onmouseover="this.style.background='#219653'" onmouseout="this.style.background='#27ae60'">Create User Account</button>
            </form>
          </div>

          <!-- All Users List -->
          <div style="background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
              <h4 style="margin: 0; color: #2c3e50; font-size: 20px;">System Users Directory</h4>
              <button @click.prevent="fetchUsersList" style="background: #ecf0f1; border: none; padding: 8px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; color: #555; cursor: pointer;">Refresh List</button>
            </div>
            
            <div style="overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                  <tr style="background: #fafbfc; border-bottom: 2px solid #eee;">
                    <th style="padding: 15px; color: #555; font-size: 14px;">Name</th>
                    <th style="padding: 15px; color: #555; font-size: 14px;">Email</th>
                    <th style="padding: 15px; color: #555; font-size: 14px;">Role</th>
                    <th style="padding: 15px; color: #555; font-size: 14px; text-align: right;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="user in usersList" :key="user.id" style="border-bottom: 1px solid #f0f0f0;">
                    <td style="padding: 15px; color: #444; font-weight: 500;">{{ user.name }}</td>
                    <td style="padding: 15px; color: #777;">{{ user.email }}</td>
                    <td style="padding: 15px;">
                      <span :style="{ background: user.role === 'admin' ? '#e1bee7' : '#dff9fb', color: user.role === 'admin' ? '#8e44ad' : '#27ae60', padding: '4px 10px', borderRadius: '20px', fontSize: '12px', fontWeight: 'bold' }">
                        {{ user.role.toUpperCase() }}
                      </span>
                    </td>
                    <td style="padding: 15px; text-align: right;">
                      <button @click.prevent="handleChangePassword(user)" style="background: transparent; border: 1px solid #3498db; color: #3498db; padding: 6px 15px; border-radius: 4px; font-size: 12px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#3498db'; this.style.color='white'" onmouseout="this.style.background='transparent'; this.style.color='#3498db'">
                        Change Password
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!usersList.length">
                    <td colspan="4" style="padding: 20px; text-align: center; color: #999;">No users found or loading...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Profile Settings Tab -->
        <div v-if="adminTab === 'profile'" style="max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
          <h3 style="color: #F14E95; font-size: 24px; margin-bottom: 20px;">Profile Settings</h3>
          <p style="color: #777; margin-bottom: 30px; font-size: 15px;">Update your personal information and profile picture.</p>
          
          <form @submit.prevent="handleUpdateProfile">
            <div style="display: flex; justify-content: center; margin-bottom: 30px;">
              <label style="cursor: pointer; position: relative; width: 120px; height: 120px; border-radius: 50%; background-color: #f5f5f5; display: flex; align-items: center; justify-content: center; overflow: visible; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                <!-- User SVG Placeholder -->
                <div v-if="!editProfileForm.image" style="color: #F14E95; display: flex; flex-direction: column; align-items: center;">
                  <svg width="60" height="60" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12ZM12 14C9.33 14 4 15.34 4 18V20H20V18C20 15.34 14.67 14 12 14Z" />
                  </svg>
                </div>
                
                <img v-if="editProfileForm.image" :src="editProfileForm.image" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                
                <div style="position: absolute; bottom: 0; right: 0; background: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                  <i class="icon-camera" style="color: #F14E95; font-size: 18px; margin: 0;"></i>
                </div>
                
                <input type="file" @change="handleProfileImageUpload" accept="image/*" style="display: none;">
              </label>
            </div>
            
            <div style="margin-bottom: 20px;">
              <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">Full Name</label>
              <input v-model="editProfileForm.name" type="text" required style="border: 1px solid #dfe6e9; height: 45px; width: 100%; padding: 0 15px; border-radius: 6px; outline: none; background: #fafbfc;" />
            </div>

            <div style="margin-bottom: 30px;">
              <button v-if="!showChangePasswordField" @click.prevent="showChangePasswordField = true" type="button" style="background: transparent; border: 1px solid #3498db; color: #3498db; padding: 12px 20px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; transition: 0.2s; width: 100%;" onmouseover="this.style.background='#3498db'; this.style.color='white'" onmouseout="this.style.background='transparent'; this.style.color='#3498db'">
                <i class="icon-key"></i> Change Password
              </button>
              
              <div v-if="showChangePasswordField" style="background: #fcf9f2; border: 1px dashed #F14E95; padding: 25px; border-radius: 8px; position: relative;">
                <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #444; font-size: 14px;">New Password <span style="color: red;">*</span></label>
                
                <div style="position: relative; margin-bottom: 15px;">
                  <input v-model="editProfileForm.password" :type="showProfilePassword ? 'text' : 'password'" placeholder="Minimum 6 characters" style="border: 1px solid #dfe6e9; height: 45px; width: 100%; padding: 0 15px; padding-right: 40px; border-radius: 6px; outline: none; background: white;" />
                  <button type="button" @click="showProfilePassword = !showProfilePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: transparent; cursor: pointer; color: #888; font-size: 16px;">
                    <span v-if="!showProfilePassword">👁️</span>
                    <span v-else>🙈</span>
                  </button>
                </div>
                <div style="display: flex; gap: 10px;">
                  <button type="button" @click.prevent="showChangePasswordField = false" style="background: #27ae60; border: none; color: white; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold;">Set Password</button>
                  <button type="button" @click.prevent="showChangePasswordField = false; editProfileForm.password = ''" style="background: white; border: 1px solid #ccc; color: #555; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold;">Cancel</button>
                </div>
              </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 40px; border-top: 1px solid #eee; padding-top: 25px;">
              <button type="button" @click.prevent="openEditProfile" style="background: #ecf0f1; border: none; color: #555; padding: 14px 30px; border-radius: 6px; font-weight: 600; font-size: 16px; cursor: pointer; transition: 0.2s;">Discard / Cancel</button>
              <button type="submit" style="flex: 1; background: #F14E95; color: white; border: none; padding: 14px 40px; border-radius: 6px; font-weight: 600; font-size: 16px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s;" onmouseover="this.style.background='#d83b7d'" onmouseout="this.style.background='#F14E95'">Save Profile</button>
            </div>
          </form>
        </div>

        <!-- System Settings Tab (Admin Only) -->
        <div v-if="adminTab === 'settings' && authUser?.role === 'admin'" style="background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
          <h3 style="color: #2c3e50; font-size: 24px; margin-bottom: 25px;">System Security & Configuration</h3>
          
          <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
              <tr style="background: #fafbfc; border-bottom: 2px solid #eee;">
                <th style="padding: 15px; color: #555; font-size: 15px;">Name</th>
                <th style="padding: 15px; color: #555; font-size: 15px; text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="padding: 20px 15px; font-weight: bold; color: #444; font-size: 16px;">System Login</td>
                <td style="padding: 20px 15px; text-align: right;">
                  <label style="position: relative; display: inline-block; width: 60px; height: 34px;">
                    <input type="checkbox" style="opacity: 0; width: 0; height: 0;" :checked="settings.login_enabled === 'true'" @change="toggleLoginSetting">
                    <span :style="{
                      position: 'absolute', cursor: 'pointer', top: 0, left: 0, right: 0, bottom: 0,
                      backgroundColor: settings.login_enabled === 'true' ? '#27ae60' : '#e74c3c',
                      transition: '.4s', borderRadius: '34px'
                    }">
                      <span :style="{
                        position: 'absolute', height: '26px', width: '26px', left: '4px', bottom: '4px',
                        backgroundColor: 'white', transition: '.4s', borderRadius: '50%',
                        transform: settings.login_enabled === 'true' ? 'translateX(26px)' : 'translateX(0)'
                      }"></span>
                    </span>
                  </label>
                </td>
              </tr>
              <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="padding: 20px 15px; font-weight: bold; color: #444; font-size: 16px;">System Register</td>
                <td style="padding: 20px 15px; text-align: right;">
                  <label style="position: relative; display: inline-block; width: 60px; height: 34px;">
                    <input type="checkbox" style="opacity: 0; width: 0; height: 0;" :checked="settings.register_enabled === 'true'" @change="toggleRegisterSetting">
                    <span :style="{
                      position: 'absolute', cursor: 'pointer', top: 0, left: 0, right: 0, bottom: 0,
                      backgroundColor: settings.register_enabled === 'true' ? '#27ae60' : '#e74c3c',
                      transition: '.4s', borderRadius: '34px'
                    }">
                      <span :style="{
                        position: 'absolute', height: '26px', width: '26px', left: '4px', bottom: '4px',
                        backgroundColor: 'white', transition: '.4s', borderRadius: '50%',
                        transform: settings.register_enabled === 'true' ? 'translateX(26px)' : 'translateX(0)'
                      }"></span>
                    </span>
                  </label>
                </td>
              </tr>
            </tbody>
          </table>
          <p style="margin-top: 20px; font-size: 14px; color: #7f8c8d; line-height: 1.6;">
            <strong>Warning:</strong> Disabling System Login/Register will result in a 404 Error warning being shown to any normal user attempting those actions. <strong style="color: red;">Only Superusers (Admins) will be authorized to access the system when these are switched off.</strong>
          </p>
        </div>

      </main>
    </div>
</template>

<style>
/* We rely on global style.css for 100% accuracy, but add mobile fixes here */

/* Hero Title Mobile Fixes */
@media screen and (max-width: 768px) {
  #fh5co-header {
    background-position-x: center !important; /* Center the couple horizontally */
    background-size: 160% 120% !important; /* Zoom in + Extra height to buffer parallax movement and hide gray bar */
    /* background-position-y is left free for parallax scroll */
  }
  #fh5co-header .display-tc h1 {
    font-size: 40px !important;
    margin-bottom: 5px !important;
  }
  #fh5co-header .display-tc h2 {
    font-size: 16px !important;
    margin-bottom: 20px !important;
  }
}

/* Simply Countdown Mobile Responsiveness Refined */
@media screen and (max-width: 480px) {
  .simply-countdown {
    display: flex !important;
    justify-content: center !important;
    flex-wrap: nowrap !important; /* Force one line on mobile if possible, or wrap nicely */
    gap: 8px !important;
    margin-top: 20px !important;
  }
  
  .simply-countdown > .simply-section {
    width: 75px !important;
    height: 75px !important;
    margin: 0 !important; /* Using gap instead */
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 50% !important;
    background: rgba(241, 78, 149, 0.8) !important; /* Ensure background is visible */
    border: 2px solid #fff !important;
  }

  /* Target the internal div if it exists */
  .simply-countdown > .simply-section > div {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
  }

  .simply-countdown > .simply-section .simply-amount {
    font-size: 22px !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    margin-bottom: 2px !important;
    color: #fff !important;
  }

  .simply-countdown > .simply-section .simply-word {
    font-size: 9px !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    color: rgba(255, 255, 255, 0.9) !important;
  }
}

@media screen and (max-width: 380px) {
  /* Ultra small screens like iPhone SE (375px) */
  .simply-countdown {
    gap: 5px !important;
  }
  .simply-countdown > .simply-section {
    width: 70px !important;
    height: 70px !important;
  }
  .simply-countdown > .simply-section .simply-amount {
    font-size: 20px !important;
  }
  .simply-countdown > .simply-section .simply-word {
    font-size: 8px !important;
  }
}

/* Off-canvas Menu Transparency Fix */
#fh5co-offcanvas {
  background: rgba(0, 0, 0, 0.7) !important; /* Semi-transparent black */
  backdrop-filter: blur(5px); /* Premium glass effect */
  -webkit-backdrop-filter: blur(5px);
}
</style>
