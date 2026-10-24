# Geodesic numerical reference

`geodesic-reference.dat` contains lines 1, 11, 21, …, 9991 of Charles F. F.
Karney's published `GeodTest-short.dat`. That file is itself a 1/50 subset of
the 500,000-row WGS-84 test set, so this is a deterministic 1,000-case sample.
It includes random, short, polar, meridional, equatorial and nearly antipodal cases.
These numbers are independently published reference data, not output from our solver.

Source: https://sourceforge.net/projects/geographiclib/files/testdata/GeodTest-short.dat.gz
Description: https://geographiclib.sourceforge.io/C++/doc/geodesic.html#testgeod
Retrieved 2026-10-24. Columns: lat1 lon1 azi1 lat2 lon2 azi2 distanceMeters
auxiliaryArcDegrees reducedLengthMeters areaSquareMeters. All ten original columns
are retained verbatim. Tests use the first seven.

All direct solutions are checked within 1 mm. Supported inverse solutions must
meet 1 mm and 1e-8 degrees. The 401 inverse cases within 2 degrees of the antipode
must raise the documented precision/convergence exception; they are not silently
skipped or accepted with a wider tolerance. 599 inverse cases remain supported.
