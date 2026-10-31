# SketchUp viewer: dependencies and loading

The checked-in viewer uses Three.js 0.185.0 directly for rendering and controls,
and OpenSKP 1.0.0 for parsing / conversion. OpenSKP uses Earcut 3.2.3 (ISC)
and fflate 0.8.3 (MIT). FlatBuffers is not included in this bundle.
Complete notices are shipped in systopic/system/legal/third-party and shown
by the central License page. components.json records source fingerprints,
pinned package integrity values and both deployed bundle hashes.

The exact original build lock is unavailable. OpenSKP's 132 function shapes,
Earcut's 37 and fflate's 20 match the pinned comparison packages in both
bundles after minified identifiers are normalized. Three.js reports revision
185; the r185 source comparison matches its rendering modules, with one
GLTFLoader function differing under bundling. Do not claim a byte-identical
rebuild from this evidence. Keep the original functioning bundles unchanged.

CMS panel.js dynamically imports data-skp-module only for data-skp-src stages.
Spaceticker dynamically imports the viewer only in SketchUp preview branches.
Ordinary CMS pages do not download this bundle. Browser ESM imports are cached
per module URL; the module is not fetched again for each preview in a session.
The bundle is about 685 kB raw / 180 kB gzip, mostly Three.js rendering code,
shaders and GLTFLoader; the application viewer wrapper is only a few kB.
Actual transfer size depends on server compression and cache headers.

Future builds must keep source, exact lockfile and build command together,
preserve dependency notices and emit cmsIcon toolbar classes. Bundle splitting
or removing the intermediate GLB/GLTFLoader path is a separate functional change.
