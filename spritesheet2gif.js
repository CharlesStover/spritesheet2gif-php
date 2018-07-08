var dimensionElements = {},
	dimensions        = ["x", "y", "zero"],
	moreInfo          = document.getElementById("more-info"),
	tileAlignments    = ["auto", "horizontal", "vertical"],
	tileElements      = {},
	x;

for (x = 0; x < dimensions.length; x++)
	dimensionElements[dimensions[x]] = document.getElementsByClassName("dimension-" + dimensions[x]);

for (x = 0; x < tileAlignments.length; x++)
	tileElements[tileAlignments[x]] = document.getElementsByClassName("tile-" + tileAlignments[x]);

document.getElementById("dimension").addEventListener(
	"keyup",
	function() {
		if (!this.value.match(/^\d+$/))
			this.value = "0";
		var x = this.value != "0",
			tile = document.getElementById("tile"),
			y, z;
		tile = tile.options[tile.selectedIndex].getAttribute("value");

		// dimension-x, dimension-zero
		if (x) {
			for (y = 0; y < dimensionElements.x.length; y++)
				dimensionElements.x[y].style.setProperty("display", "block");
			for (y = 0; y < dimensionElements.zero.length; y++)
				dimensionElements.zero[y].style.setProperty("display", "none");
		}
		else {
			for (y = 0; y < dimensionElements.x.length; y++)
				dimensionElements.x[y].style.setProperty("display", "none");
			for (y = 0; y < dimensionElements.zero.length; y++)
				dimensionElements.zero[y].style.setProperty("display", "inline");
		}

		// Hide unselected tiles that happened to match the new dimension.
		for (y = 0; y < tileAlignments.length; y++) {
			if (tileAlignments[y] != tile) {
				for (z = 0; z < tileElements[tileAlignments[y]].length; z++)
					tileElements[tileAlignments[y]][z].style.setProperty("display", "none");
			}
		}

		// dimension-y
		for (y = 0; y < dimensionElements.y.length; y++)
			dimensionElements.y[y].firstChild.nodeValue = this.value + " pixel" + (this.value != "1" ? "s" : "");
	}
);

document.getElementById("more-info-link").addEventListener(
	"click",
	function() {
		if (this.innerText.match(/^\+ /)) {
			this.innerText = "- Less Info";
			moreInfo.style.setProperty("display", "block");
		}
		else {
			this.innerText = "+ More Info";
			moreInfo.style.setProperty("display", "none");
		}
	}
);

document.getElementById("tile").addEventListener(
	"change",
	function() {
		var dimensionX = document.getElementById("dimension").value != "0",
			tileValue = this.options[this.selectedIndex].getAttribute("value"),
			align, element, x, y;
		for (x = 0; x < tileAlignments.length; x++) {
			align = tileAlignments[x];
			for (y = 0; y < tileElements[align].length; y++) {
				element = tileElements[align][y];
				element.style.setProperty(
					"display",
					align == tileValue &&
					(
						!element.className.match(/dimension\-x/) ||
						dimensionX
					) ? (
						element.nodeName.toLowerCase() == "span" ?
						"inline" :
						"block"
					) :
					"none"
				);
			}
		}
	}
);