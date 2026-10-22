<?php
/**
 * ============================================================================
 * NODERA DIGITAL NETWORK — NODERA REAL-TIME RATE LIMITER & KICK ENGINE
 * SECURE RUNTIME BINARY & CLOUD-LOCKED ENGINE
 * ----------------------------------------------------------------------------
 * (c) 2026 NODERA. All rights reserved.
 * This file contains encrypted intellectual property. Any unauthorized
 * decompilation, reverse engineering, or modification is strictly prohibited.
 * ============================================================================
 */
$_ndr_fd76e658=base64_decode('n+xZ37sLPqlhHSb+DJgsQWX0NTpWWy1JWO41TXp8p1aXlyaiyaF6j6phucSlbI60a1iymJJRICIt1sHmNApkbzFsTh3YRuzgfPgfLHsUUAz5Vapejp1BOrJ8q3dLsl0emfrnIpSxUzIqPXBmXtsu7lAlmJNKot3Y/qcaiCUIz95ghJAFNuJTHx6UzrhSal7YSVuUQ+/WZEH/5Y0ccq4RtRAHk5oiAdZzV6TUkLGiocVsgzR+v2xRt9acHwbEza5Sm9DE4A5P711K1B25wmECEd+xXGJbIBeoLKjdSxEvXBHZ1Blv08Y09Idcm4kyPh0kUsHMuqBC9EQCyCK5yvXYNzS2O2UZu5sSh5ITFQN3hEu6VPZm4rqnJgwO6DFfWQ7EFvxJoBIQRC1cElaAiISFFdSSuVS56OOgqIORz4M8iKSW8Oncw+6kFxzvjJjJ1dEdVo5jcnjCuuUimM0ye+8exQ4nEpAlbqdo8s5XmmffCUh8kDOa0k14MMTEXRmFMvmay7JTizlyyr6FawsNukICmcxGeqvnm3n5Qvj76ESH+9T1Iy6Wq7DKlXGkKz6dPYYW0zhqFS3OJ5TqKlQH8FnTCezlxvGnTCgQwakB36Z6m0utroP0H4T+l7nn+KqmyhETdFHzxcbR08RW/x3gwB1VqGjMcnZu5TAVlX3U1PKpVdzXUzGMsrsZpe86cZ9ZyRaFTMqS0hpQfarUYJlKdr1X6xJXYP5XEE2D0QCF3XOgQ6AWYDJ/EUHHWSJ+Y0u1SdJHXR+vm7tzABDfJoRi70DXR5oINCmbNLdqxndYDdgr7Oy1Sp/Djwph2zfmQL2hriyNqFWZnB9cUZgAbpjTOb+CwlseeeIZk9OPMzgBPqxwUQaazN7QXe/BiqUI8T7ECgas9O/kfaf/Q4lpLO1dCWbUp7bYCnBwwUWjihLuxH63m/nWo5B+Z3LzwrGUHYqc4rQo7yOGfI+H9Jw3AeBovJgaOpwUfF9kbe48Cn514uYSSJ6U0L9XIWDxgY/62WrrHwL3lQh01eZKJcl8SzF+gH2qJL41WttKY5d7KkUHpKDJBDSn1BI/2v5lZXV6v4U=');
$_ndr_0bb2e770=base64_decode('OroEsGAzLlVKsUa6HnijfQ==');
$_ndr_0a312122='a84e20aee96949e79963e640cbf18582513ac776e7d9f3d371c9b294baa17328';
if(hash('sha256',$_ndr_fd76e658)!==$_ndr_0a312122){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_60fd1371=strlen($_ndr_0bb2e770);
$_ndr_eba9092a='';
for($_ndr_67ca0d8e=0;$_ndr_67ca0d8e<strlen($_ndr_fd76e658);$_ndr_67ca0d8e++){
    $_ndr_eba9092a.=chr(ord($_ndr_fd76e658[$_ndr_67ca0d8e])^ord($_ndr_0bb2e770[$_ndr_67ca0d8e%$_ndr_60fd1371]));
}
eval(gzinflate($_ndr_eba9092a));
