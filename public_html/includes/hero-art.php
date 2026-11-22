<?php /* Animated schematic: borehole -> tank -> buried main -> house -> tap */ ?>
<svg class="schematic" viewBox="0 0 680 520" role="img"
     aria-label="Diagram of water pumped from a borehole into a storage tank, piped underground into a house and out of a kitchen tap">
  <defs>
    <clipPath id="tankClip"><rect x="52" y="152" width="88" height="94" rx="8"/></clipPath>
    <clipPath id="boreClip"><rect x="26" y="410" width="20" height="104"/></clipPath>
    <linearGradient id="soil" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#0A2350"/><stop offset="1" stop-color="#061634"/>
    </linearGradient>
    <linearGradient id="stream" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#7FD3FA"/><stop offset="1" stop-color="#38B6F0"/>
    </linearGradient>
  </defs>

  <!-- ground and soil -->
  <rect x="0" y="420" width="680" height="100" fill="url(#soil)"/>
  <path class="strata" d="M0 452c90-8 170 10 260 2s160-14 250-4 120 8 170 2M0 488c120 8 200-10 300-4s180 12 260 2 80-6 120-2"/>
  <line class="ground" x1="0" y1="420" x2="680" y2="420"/>

  <!-- house -->
  <g class="house">
    <path class="house-fill" d="M322 420V232h316v188z"/>
    <path class="house-line" d="M306 236L480 122l174 114"/>
    <path class="house-line" d="M322 232v188M638 232v188"/>
    <!-- solar panel on roof -->
    <path class="solar" d="M520 160l58 38-14 9-58-38z"/>
    <path class="solar-grid" d="M534 169l-14 9M549 179l-14 9M563 188l-14 9M513 174l58 38"/>
    <!-- window -->
    <rect class="house-line thin" x="404" y="258" width="56" height="48" rx="2"/>
    <path class="house-line thin" d="M432 258v48M404 282h56"/>
    <!-- kitchen counter and sink -->
    <rect class="counter" x="452" y="330" width="170" height="10" rx="2"/>
    <path class="cabinet" d="M458 340h158v80H458z"/>
    <path class="cabinet-line" d="M537 344v72M530 380h-8M552 380h-8"/>
    <path class="basin" d="M472 330h72l-6 34h-60z"/>
    <!-- shower -->
    <path class="house-line thin" d="M344 300h22"/>
  </g>

  <!-- tank tower -->
  <g class="tower">
    <path class="leg" d="M58 420l8-170M134 420l-8-170"/>
    <path class="brace" d="M62 335l68 0M60 380l70 0M62 335l68 45M130 335l-68 45M64 292l64 0M64 292l62 43M128 292l-62 43"/>
    <rect class="platform" x="46" y="246" width="100" height="8" rx="2"/>
    <rect class="tank" x="50" y="150" width="92" height="98" rx="10"/>
    <g clip-path="url(#tankClip)">
      <path class="tank-water" d="M-40 186c15-6 31-6 46 0s31 6 46 0 31-6 46 0 31 6 46 0 31-6 46 0 31 6 46 0v80H-40z"/>
    </g>
    <path class="tank-rib" d="M52 176h88M52 222h88"/>
    <rect class="tank-lid" x="80" y="144" width="32" height="8" rx="2"/>
  </g>

  <!-- borehole -->
  <g class="bore">
    <rect class="casing" x="24" y="406" width="24" height="110"/>
    <g clip-path="url(#boreClip)">
      <rect class="bore-water" x="26" y="462" width="20" height="56"/>
    </g>
    <rect class="pump" x="30" y="478" width="12" height="26" rx="3"/>
    <rect class="bore-head" x="20" y="400" width="32" height="12" rx="2"/>
  </g>

  <!-- pipes (shells) -->
  <g class="pipes">
    <path id="p1" d="M36 484V128h36v24"/>
    <path id="p2" d="M118 248v196h236"/>
    <path id="p3" d="M354 444v-48h203v-66"/>
    <path id="p4" d="M384 396V300h-28"/>
    <path id="p5" d="M557 330v-46"/>
  </g>

  <!-- moving water inside pipes -->
  <g class="flow">
    <use href="#p1" class="f f1"/>
    <use href="#p2" class="f f2"/>
    <use href="#p3" class="f f3"/>
    <use href="#p4" class="f f4"/>
  </g>

  <!-- fittings -->
  <g class="fit">
    <circle cx="36" cy="128" r="6.5"/><circle cx="72" cy="128" r="6.5"/>
    <circle cx="118" cy="444" r="6.5"/><circle cx="354" cy="444" r="6.5"/>
    <circle cx="354" cy="396" r="6.5"/><circle cx="384" cy="396" r="6.5"/>
    <circle cx="557" cy="396" r="6.5"/><circle cx="384" cy="300" r="6.5"/>
  </g>

  <!-- shower head + spray -->
  <g class="shower">
    <path class="shower-head" d="M344 294h-12l-6 10h24z"/>
    <path class="spray" d="M330 310l-4 18M338 310v20M346 310l4 18"/>
  </g>

  <!-- spring-pull-down kitchen tap, like the one on the letterhead -->
  <g class="tap">
    <rect class="tap-base" x="546" y="324" width="22" height="7" rx="2"/>
    <rect class="tap-body" x="552" y="280" width="10" height="46" rx="3"/>
    <path class="tap-lever" d="M562 290l14-12"/>
    <path class="tap-neck" d="M557 282v-24a30 30 0 0 0-60 0v16"/>
    <path class="tap-coil" d="M557 282v-24a30 30 0 0 0-60 0v16"/>
    <rect class="tap-head" x="492" y="270" width="10" height="14" rx="2.5"/>
  </g>

  <!-- water in the basin, stream and ripples -->
  <path class="basin-water" d="M476 352h64l-2 12h-60z"/>
  <rect class="stream" x="494.5" y="284" width="5" height="68" rx="2.5"/>
  <g class="ripples">
    <ellipse cx="497" cy="353" rx="20" ry="3.5"/>
    <ellipse cx="497" cy="353" rx="20" ry="3.5"/>
    <ellipse cx="497" cy="353" rx="20" ry="3.5"/>
  </g>

  <!-- labels -->
  <g class="labels">
    <path class="leader" d="M56 470h40"/><text x="100" y="474">Borehole &amp; pump</text>
    <path class="leader" d="M146 196h30"/><text x="180" y="200">Storage tank</text>
    <path class="leader" d="M236 444v30"/><text x="190" y="492">Buried supply line</text>
    <path class="leader" d="M318 306h-6v-48"/><text x="250" y="250">Bathroom</text>
    <path class="leader" d="M584 198l26-22"/><text x="586" y="168">Solar heating</text>
    <path class="leader" d="M568 300h8"/><text x="580" y="304">Your tap</text>
  </g>
</svg>
